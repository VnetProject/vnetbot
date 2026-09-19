"""
Thin wrapper around the `pasarguard` SDK to create/manage admin (reseller)
accounts on a PasarGuard panel.

The panel's admin-role system (roles/permissions, e.g. "owner",
"administrator", "operator") is fetched live from the panel itself
(`get_admin_roles`) so the admin picks from real buttons instead of typing a
role name blind. Because the exact SDK method/field names can differ
slightly between `pasarguard` package versions, the lookups below try a
short list of plausible names and fall back gracefully (free-text role entry)
if none of them exist on your installed version - see the `# tries:` comments.
"""
from __future__ import annotations

import logging

from pasarguard import AdminCreate, AdminModify, PasarguardAPI

log = logging.getLogger("panel_client")


async def _client(panel) -> PasarguardAPI:
    return PasarguardAPI(base_url=panel.address, verify=True, timeout=20.0)


async def _token(api: PasarguardAPI, panel) -> str:
    tok = await api.get_token(username=panel.username, password=panel.password)
    return tok.access_token


async def test_panel_connection(panel) -> tuple[bool, str | None]:
    """Try to log in to the panel. Returns (ok, error_message)."""
    try:
        async with await _client(panel) as api:
            token = await _token(api, panel)
            admin = await api.get_current_admin(token=token)
            log.info("Connected to panel %s as %s", panel.name, getattr(admin, "username", "?"))
            return True, None
    except Exception as e:
        return False, str(e)


async def get_admin_roles(panel) -> list[tuple[str, str]]:
    """Return [(role_id_or_key, display_name), ...] for real roles that exist
    on this panel, or an empty list if none could be fetched (caller should
    fall back to manual text entry in that case).
    """
    # tries: get_admin_roles(), get_all_admin_roles(), admin_roles()
    method_names = ["get_admin_roles", "get_all_admin_roles", "admin_roles"]
    try:
        async with await _client(panel) as api:
            token = await _token(api, panel)
            for name in method_names:
                method = getattr(api, name, None)
                if method is None:
                    continue
                try:
                    result = await method(token=token)
                except TypeError:
                    result = await method()
                except Exception as e:
                    log.warning("panel role lookup via %s failed: %s", name, e)
                    continue

                items = getattr(result, "roles", None) or result
                roles = []
                for item in items:
                    rid = getattr(item, "id", None) or getattr(item, "role_id", None) or getattr(item, "name", None)
                    rname = getattr(item, "name", None) or str(rid)
                    if rid is not None:
                        roles.append((str(rid), rname))
                if roles:
                    return roles
    except Exception as e:
        log.warning("could not connect to fetch admin roles: %s", e)
    return []


async def create_reseller_admin(panel, username: str, password: str, role) -> None:
    """Create a new admin (reseller) account on the panel.
    `role` is whatever value was stored on the Plan (the role id returned by
    get_admin_roles(), or free text if role auto-detection wasn't available).
    """
    async with await _client(panel) as api:
        token = await _token(api, panel)

        kwargs = dict(username=username, password=password, is_sudo=False)
        # Try the most likely field names for assigning a role, in order.
        # AdminCreate on newer PasarGuard versions exposes role_id (FK to the
        # panel's admin_role table); older/renamed variants might use `role`.
        for field in ("role_id", "role"):
            try:
                value = int(role) if field == "role_id" and str(role).isdigit() else role
                payload = AdminCreate(**kwargs, **{field: value})
                await api.create_admin(payload, token=token)
                return
            except TypeError:
                continue
            except Exception:
                raise
        # No matching field found on this SDK version - create without a role
        # and let the panel apply its default; verify manually afterwards.
        log.warning("AdminCreate has neither 'role_id' nor 'role' field on this SDK version; created without role.")
        await api.create_admin(AdminCreate(**kwargs), token=token)


async def disable_reseller_admin(panel, username: str) -> None:
    async with await _client(panel) as api:
        token = await _token(api, panel)
        await api.modify_admin(username, AdminModify(is_disabled=True), token=token)


async def enable_reseller_admin(panel, username: str) -> None:
    async with await _client(panel) as api:
        token = await _token(api, panel)
        await api.modify_admin(username, AdminModify(is_disabled=False), token=token)


async def change_reseller_password(panel, username: str, new_password: str) -> None:
    async with await _client(panel) as api:
        token = await _token(api, panel)
        await api.modify_admin(username, AdminModify(password=new_password), token=token)


async def delete_reseller_admin(panel, username: str) -> None:
    async with await _client(panel) as api:
        token = await _token(api, panel)
        await api.remove_admin(username, token=token)


async def get_admin_usage_bytes(panel, username: str) -> int:
    """Return cumulative used traffic (bytes) for this admin's created users.
    TODO: verify the field name against your installed SDK version:
    ``python3 -c "from pasarguard import AdminDetails; print(AdminDetails.model_fields)"``
    """
    async with await _client(panel) as api:
        token = await _token(api, panel)
        admins = await api.get_admins(token=token)
        for admin in admins:
            if admin.username == username:
                return getattr(admin, "used_traffic", 0) or 0
    return 0


async def create_test_user(panel, data_limit_mb: float, days: int):
    """Create a single test user/config on the given panel (not a reseller
    admin - a normal end-user config), returning the created user object.
    """
    from pasarguard import Tools, UserCreate, UserStatus

    async with await _client(panel) as api:
        token = await _token(api, panel)
        user = await api.create_user_in_all_groups(
            UserCreate(
                username=Tools.random_username(prefix="test"),
                data_limit=Tools.mb(data_limit_mb),
                expire=Tools.days(days),
                status=UserStatus.ACTIVE,
                note="Free trial via reseller bot",
            ),
            token=token,
        )
        return user
