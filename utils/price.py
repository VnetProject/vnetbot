import aiohttp

COINGECKO_URL = "https://api.coingecko.com/api/v3/simple/price?ids=the-open-network&vs_currencies=usd"


async def get_ton_usd_price() -> float:
    """Fetch current TON price in USD. Raises on network failure - caller
    should catch and show a friendly error to the user."""
    async with aiohttp.ClientSession() as session:
        async with session.get(COINGECKO_URL, timeout=10) as resp:
            data = await resp.json()
            return float(data["the-open-network"]["usd"])


async def toman_to_ton(amount_toman: float, usd_to_toman: float) -> float:
    """Convert a Toman amount to the equivalent TON amount using the
    admin-configured USD/Toman rate and the live TON/USD price."""
    ton_usd = await get_ton_usd_price()
    usd_amount = amount_toman / usd_to_toman
    ton_amount = usd_amount / ton_usd
    return round(ton_amount, 4)
