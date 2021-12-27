<?php
namespace Marble\Entity;

interface Entity
{
    /**
     * An entity's identity may only be NULL if it hasn't been persisted.
     * This library assumes that entities without identities have not been persisted (yet).
     *
     * @return Identifier|null
     */
    public function getId(): ?Identifier;
}
