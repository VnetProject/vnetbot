from aiogram.fsm.state import State, StatesGroup


class BuyPanel(StatesGroup):
    choosing_plan = State()
    waiting_username = State()
    waiting_password = State()


class Wallet(StatesGroup):
    waiting_card_amount = State()
    waiting_ton_amount = State()
    waiting_receipt = State()


class Support(StatesGroup):
    waiting_message = State()


class ManageReseller(StatesGroup):
    waiting_new_password = State()
    waiting_usage_limit = State()
    waiting_expire_date = State()


# ---- Admin panel FSM ----

class AdminPlan(StatesGroup):
    name = State()
    price_per_gb = State()
    price_per_hour = State()
    user_limit = State()
    choosing_panel = State()
    choosing_role = State()
    min_balance = State()
    editing_field = State()


class AdminPanelForm(StatesGroup):
    name = State()
    address = State()
    username = State()
    password = State()
    editing_field = State()


class AdminSettings(StatesGroup):
    card_number = State()
    ton_wallet = State()
    min_charge = State()
    max_charge = State()
    usd_rate = State()
    test_gb = State()
    test_days = State()
    editing_text_key = State()
    editing_text_value = State()


class AdminUserSearch(StatesGroup):
    waiting_id = State()
    waiting_balance_amount = State()
    waiting_message = State()


class AdminBroadcast(StatesGroup):
    waiting_content = State()


class AdminChannel(StatesGroup):
    waiting_mandatory = State()
    waiting_report = State()


class AdminButtons(StatesGroup):
    waiting_label = State()
    waiting_emoji = State()
