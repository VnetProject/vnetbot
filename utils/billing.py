import datetime
import logging

from sqlalchemy import select

from database.db import get_session
from database.models import Panel, Plan, Reseller, ResellerStatus, User
from utils.panel_client import disable_reseller_admin, get_admin_usage_bytes

log = logging.getLogger("billing")

GB = 1024 ** 3


async def run_billing_cycle(bot):
    """Runs every hour. For every ACTIVE reseller:
      1. Fetch current cumulative traffic from the panel.
      2. Charge (delta_gb * price_per_gb) + (1 hour * price_per_hour) from
         the owning user's wallet balance.
      3. Enforce usage_limit_toman (per-reseller spend cap) and expire_at.
      4. If balance <= 0 (or cap/expiry hit), disable the admin on the panel
         and mark the reseller DISABLED - never deleted.
    """
    async with get_session() as session:
        result = await session.execute(select(Reseller).where(Reseller.status == ResellerStatus.ACTIVE))
        resellers = result.scalars().all()

        now = datetime.datetime.utcnow()

        for reseller in resellers:
            # Explicit fetches instead of `reseller.plan` / `.user` / `.panel`
            # relationship access - SQLAlchemy's async ORM can't lazy-load
            # those outside a sync greenlet and would crash the whole cycle.
            plan = await session.get(Plan, reseller.plan_id)
            user = await session.get(User, reseller.user_id)
            panel = await session.get(Panel, reseller.panel_id)

            if reseller.expire_at and now >= reseller.expire_at:
                await _disable(session, bot, reseller, panel, user, "تاریخ انقضای نمایندگی شما به پایان رسید.")
                continue

            try:
                used_bytes = await get_admin_usage_bytes(panel, reseller.panel_username)
            except Exception as e:
                log.warning("usage fetch failed for %s: %s", reseller.panel_username, e)
                used_bytes = reseller.last_used_traffic_bytes

            delta_bytes = max(0, used_bytes - reseller.last_used_traffic_bytes)
            delta_gb = delta_bytes / GB
            traffic_cost = delta_gb * plan.price_per_gb
            hourly_cost = plan.price_per_hour
            total_cost = traffic_cost + hourly_cost

            user.balance -= total_cost
            reseller.spent_toman += total_cost
            reseller.last_used_traffic_bytes = used_bytes
            reseller.last_billed_at = now

            if reseller.usage_limit_toman and reseller.spent_toman >= reseller.usage_limit_toman:
                await _disable(session, bot, reseller, panel, user,
                                "سقف مصرف تعیین شده برای این نمایندگی به پایان رسید.")
                continue

            if user.balance <= 0:
                await _disable(session, bot, reseller, panel, user, "موجودی کیف پول شما به پایان رسید.")
                continue

        await session.commit()


async def _disable(session, bot, reseller: Reseller, panel: Panel, user: User, reason: str):
    try:
        await disable_reseller_admin(panel, reseller.panel_username)
    except Exception as e:
        log.warning("failed to disable %s on panel: %s", reseller.panel_username, e)
    reseller.status = ResellerStatus.DISABLED
    try:
        await bot.send_message(
            user.telegram_id,
            f"⛔️ نمایندگی «{reseller.panel_username}» غیرفعال شد.\nدلیل: {reason}\n"
            "پس از شارژ کیف پول می‌توانید آن را دوباره فعال کنید.",
        )
    except Exception:
        pass
