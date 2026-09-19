from sqlalchemy.ext.asyncio import AsyncSession, async_sessionmaker, create_async_engine

from config import DATABASE_URL
from database.models import Base, Settings, DEFAULT_TEXTS

engine = create_async_engine(DATABASE_URL, echo=False, pool_pre_ping=True)
async_session = async_sessionmaker(engine, expire_on_commit=False)


async def init_db():
    async with engine.begin() as conn:
        await conn.run_sync(Base.metadata.create_all)

    async with async_session() as session:
        settings = await session.get(Settings, 1)
        if settings is None:
            session.add(Settings(id=1, texts=dict(DEFAULT_TEXTS)))
            await session.commit()


def get_session() -> AsyncSession:
    return async_session()
