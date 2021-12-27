<?php
namespace Marble\EntityManager\UnitOfWork;

enum EntityState
{
    case NEW;
    case FETCHED;
    case INSERTED;
    case UPDATED;
    case REMOVED;

    public function toPersisted(): self
    {
        return $this === self::NEW ? self::INSERTED : self::UPDATED;
    }
}
