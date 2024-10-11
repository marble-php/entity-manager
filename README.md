## Marble Entity Manager

### Introduction

This library provides some of the great features of many ORM frameworks — entity manager, unit of work,
identity map, repository factory, query caching — without the object-relational mapping itself.
It’s up to you to implement the actual reading and writing of entity data from and to whatever
persistence layer you’re using.

### Installation

Use Composer to install: `composer require marble/entity-manager`

This library requires PHP 8.1.

### How to use

1. All your entity classes should implement the `Entity` interface. For identifiers you may use the 
provided `SimpleId` or `Ulid` classes, or any other implementation of the `Identifier` interface.
2. Create a class that implements `EntityReader` for every entity class that you want to fetch
from your application code or from other readers. See [Fetching](#fetching) for more details.
3. For every entity class, create a class that implements `EntityWriter`, unless such entities are
always written and deleted by other writers. See [Persisting](#persisting) and [Removing](#removing)
for more details.
4. Implement the `EntityIoProvider` interface and have it return the correct `EntityReader` and 
`EntityWriter` for a given `Entity`. Of course one class may implement both interfaces.

### Persisting

- To persist a newly created entity, `add` it to its repository or pass it to `EntityManager::persist`.
Preexisting, fetched entities don’t need to be re-registered; any changes to them will be automatically
detected. To actually write data changes to your persistence layer, call `EntityManager::flush`; 
until then all changes are in memory only. Note that an entity’s writer will only be called if the entity
indeed has changes or must be removed.
- Multiple entities in the same entity class hierarchy must never have the same identifier. It’s okay if
  new entities don’t have an identifier yet, but once an entity is persisted it must have an identifier.
- A flush order is calculated by sorting known entities such that a given entity’s associations are
all ranked higher than the entity itself. As such, when persisting an entity in `EntityWriter::write`, 
its associated entities will have been passed to their writers already, and will have an id. This 
algorithm does not allow circular entity associations. 
- You may use a writer to persist not just its own entity but particular associated entities ("child entities")
as well, e.g. an aggregate root’s writer also persisting other entities in the aggregate. Make sure
to call `markPersisted` on the passed `WriteContext` to let the unit of work know that
these were indeed persisted. `EntityIoProvider::getWriter` can return `null` for entity classes
that are always persisted by other writers.
- Your entity writer should throw an `EntitySkippedException` when you need to leave the writing or removing
of the entity to a parent entity’s writer later in the flush order. The library will check that all
necessary writes and removals are done by the end of the flush.

### Fetching

- Fetch entities by getting their repository from the entity manager and passing any kind of object
to one of the `fetch*` methods. The object represents the query to be executed. It will be passed
to the entity reader, where you should handle it appropriately. A special case is objects implementing
the `Identifier` interface; these are only allowed on the `fetchOne` method.
- Your query classes may be as simple or complex as you find helpful, and may carry any information needed
for the corresponding entity reader to do correct data retrieval (e.g. construct SQL statements), 
including sorting and pagination. A `Criteria` class is provided by this library, to represent 
a set of values to filter on.
- Entity readers should not instantiate entities themselves; instead, they pass identifier/data combinations
into the `DataCollector`. After the entity reader is done with data retrieval, the repository will make sure
entities are instantiated, registered in the unit of work, added to the identity map and returned.
- When fetching an entity, use the passed `ReadContext` to access other repositories and
fetch associated entities through them. Any associated sub-entity is replaced with its equivalent 
in the identity map, if it exists there already. So even with nested associations, only one instance 
of a particular entity will exist at any time.
- __IMPORTANT:__ One major limitation of this library compared to ORM libraries, is that all `fetch*` 
calls to the repository are forwarded to your entity reader. This means queries only operate directly 
on the persistence layer, and will ignore in-memory, pre-flush changes and additions in the unit of work.
There is currently no extension point in the library to circumvent this limitation.

### Removing

- To remove an entity, `remove` it from its repository or pass it to `EntityManager::remove`. Again, only
on flush are deletions actually executed through `EntityWriter::delete`.
- You may use a writer to delete not just its own entity but associated sub-entities as well, whether 
explicitly or via database-level cascade rules. Make sure to call `markRemoved` on the passed `DeleteContext`
to let the unit of work know that these were indeed removed.
- Removed entities are removed from the identity map. Fetching the same entity again will return a new
instance.
