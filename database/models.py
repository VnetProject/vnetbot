from __future__ import annotations

import datetime
import enum

from sqlalchemy import (
    BigInteger,
    Boolean,
    DateTime,
    Float,
    ForeignKey,
    Integer,
    String,
    Text,
    JSON,
    Enum,
)
from sqlalchemy.orm import DeclarativeBase, Mapped, mapped_column, relationship


class Base(DeclarativeBase):
    pass


class User(Base):
    """A telegram end-user of the bot (buyer of reseller panels)."""
    __tablename__ = "users"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    telegram_id: Mapped[int] = mapped_column(BigInteger, unique=True, index=True)
    username: Mapped[str | None] = mapped_column(String(64), nullable=True)
    balance: Mapped[float] = mapped_column(Float, default=0)
    is_banned: Mapped[bool] = mapped_column(Boolean, default=False)
    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, default=datetime.datetime.utcnow)

    resellers: Mapped[list["Reseller"]] = relationship(back_populates="user")
    transactions: Mapped[list["Transaction"]] = relationship(back_populates="user")


class Panel(Base):
    """A PasarGuard panel instance the bot can create admins on."""
    __tablename__ = "panels"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    name: Mapped[str] = mapped_column(String(128))
    address: Mapped[str] = mapped_column(String(256))          # main API address
    resell_address: Mapped[str | None] = mapped_column(String(256), nullable=True)  # shown to end users to log in
    username: Mapped[str] = mapped_column(String(128))         # sudo admin username used by bot
    password: Mapped[str] = mapped_column(String(256))
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)

    plans: Mapped[list["Plan"]] = relationship(back_populates="panel")


class Plan(Base):
    __tablename__ = "plans"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    name: Mapped[str] = mapped_column(String(128))
    price_per_gb: Mapped[float] = mapped_column(Float)
    price_per_hour: Mapped[float] = mapped_column(Float)
    user_limit: Mapped[int] = mapped_column(Integer)          # max users the reseller admin may create
    panel_id: Mapped[int] = mapped_column(ForeignKey("panels.id"))
    admin_role: Mapped[str] = mapped_column(String(64))       # e.g. "admin" / "operator" - verify against panel roles
    min_balance: Mapped[float] = mapped_column(Float)         # min wallet balance required to buy this plan
    is_active: Mapped[bool] = mapped_column(Boolean, default=True)

    panel: Mapped["Panel"] = relationship(back_populates="plans")


class ResellerStatus(str, enum.Enum):
    ACTIVE = "active"
    DISABLED = "disabled"   # out of balance / expired - NOT deleted
    DELETED = "deleted"


class Reseller(Base):
    """A purchased reseller/admin account on a panel, owned by a bot user."""
    __tablename__ = "resellers"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    plan_id: Mapped[int] = mapped_column(ForeignKey("plans.id"))
    panel_id: Mapped[int] = mapped_column(ForeignKey("panels.id"))

    panel_username: Mapped[str] = mapped_column(String(128))
    panel_password: Mapped[str] = mapped_column(String(256))
    admin_role: Mapped[str] = mapped_column(String(64))

    status: Mapped[ResellerStatus] = mapped_column(Enum(ResellerStatus), default=ResellerStatus.ACTIVE)

    usage_limit_toman: Mapped[float | None] = mapped_column(Float, nullable=True)  # optional spend cap
    spent_toman: Mapped[float] = mapped_column(Float, default=0)                    # spent since last limit reset

    expire_at: Mapped[datetime.datetime | None] = mapped_column(DateTime, nullable=True)

    last_used_traffic_bytes: Mapped[int] = mapped_column(BigInteger, default=0)  # last seen cumulative traffic
    last_billed_at: Mapped[datetime.datetime] = mapped_column(DateTime, default=datetime.datetime.utcnow)

    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, default=datetime.datetime.utcnow)

    user: Mapped["User"] = relationship(back_populates="resellers")
    plan: Mapped["Plan"] = relationship()
    panel: Mapped["Panel"] = relationship()


class TransactionType(str, enum.Enum):
    CHARGE_CARD = "charge_card"
    CHARGE_TON = "charge_ton"
    CONSUME = "consume"
    ADMIN_ADJUST = "admin_adjust"
    REFUND = "refund"


class TransactionStatus(str, enum.Enum):
    PENDING = "pending"
    APPROVED = "approved"
    REJECTED = "rejected"


class Transaction(Base):
    __tablename__ = "transactions"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    amount: Mapped[float] = mapped_column(Float)
    type: Mapped[TransactionType] = mapped_column(Enum(TransactionType))
    status: Mapped[TransactionStatus] = mapped_column(Enum(TransactionStatus), default=TransactionStatus.PENDING)
    note: Mapped[str | None] = mapped_column(Text, nullable=True)
    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, default=datetime.datetime.utcnow)

    user: Mapped["User"] = relationship(back_populates="transactions")


class MandatoryChannel(Base):
    __tablename__ = "mandatory_channels"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    chat_id: Mapped[str] = mapped_column(String(64))   # e.g. @channel or -100123...
    title: Mapped[str | None] = mapped_column(String(128), nullable=True)


class SupportMessage(Base):
    __tablename__ = "support_messages"

    id: Mapped[int] = mapped_column(Integer, primary_key=True)
    user_id: Mapped[int] = mapped_column(ForeignKey("users.id"))
    message: Mapped[str] = mapped_column(Text)
    created_at: Mapped[datetime.datetime] = mapped_column(DateTime, default=datetime.datetime.utcnow)


class Settings(Base):
    """Singleton row (id=1) holding all bot-wide configurable settings."""
    __tablename__ = "settings"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, default=1)

    # payments
    card_number: Mapped[str | None] = mapped_column(String(64), nullable=True)
    ton_wallet: Mapped[str | None] = mapped_column(String(128), nullable=True)
    min_charge: Mapped[float] = mapped_column(Float, default=50000)
    max_charge: Mapped[float] = mapped_column(Float, default=5000000)
    usd_to_toman: Mapped[float] = mapped_column(Float, default=60000)  # manually set by admin

    # test config
    # NOTE: column name kept as *_gb for backward compatibility (no DB
    # migration needed) but the value is interpreted in MEGABYTES everywhere
    # in the bot/admin UI now.
    test_data_limit_gb: Mapped[float] = mapped_column(Float, default=500)
    test_days: Mapped[int] = mapped_column(Integer, default=1)
    test_panel_id: Mapped[int | None] = mapped_column(ForeignKey("panels.id"), nullable=True)
    test_enabled: Mapped[bool] = mapped_column(Boolean, default=True)

    # channels
    report_channel: Mapped[str | None] = mapped_column(String(64), nullable=True)

    # texts - json dict of key -> text, editable from admin panel
    texts: Mapped[dict] = mapped_column(JSON, default=dict)

    # ui - premium emoji ids & button colors, json
    premium_emojis: Mapped[dict] = mapped_column(JSON, default=dict)
    button_colors: Mapped[dict] = mapped_column(JSON, default=dict)


DEFAULT_TEXTS = {
    "welcome": "به ربات فروش نمایندگی خوش آمدید 👋",
    "rules": "قوانین:\n1. مسئولیت استفاده از سرویس با کاربر است.\n2. فروش مجدد بدون اجازه ممنوع است.\n3. پشتیبانی فقط در ساعات کاری پاسخگو است.",
    "faq": "سوالات متداول به زودی تکمیل می‌شود.",
    "insufficient_balance": "موجودی شما کافی نیست ❌\nحداقل موجودی برای این پلن: {min_balance} تومان",
    "support_prompt": "پیام خود را برای پشتیبانی ارسال کنید:",
    "support_received": "پیام شما برای پشتیبانی ارسال شد ✅",
}
