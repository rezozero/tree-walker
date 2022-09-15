## 1.2.2 (2022-09-15)

### Bug Fixes

* Added default ignored fields and methods when no serialization groups are defined ([21940f7](https://github.com/rezozero/tree-walker/commit/21940f76c479aef99c93c4b63a45c679d1a7818c))

## 1.2.1 (2022-08-29)

### Bug Fixes

* Do not set level to `\INF` because its value can be used in top-level applications ([5331036](https://github.com/rezozero/tree-walker/commit/53310366976f6e7b5a7dbe36994a918e629a865a))

## 1.2.0 (2022-08-29)

### Features

* Added `StoppableDefinition` interface to prevent walker to collect children after being invoked ([b1fd429](https://github.com/rezozero/tree-walker/commit/b1fd429336d4b10bfe71498b84c494eaf6b8eee8))
* Introduced `AbstractCycleAwareWalker` to detect cyclic children collection based on `spl_object_id` ([607520a](https://github.com/rezozero/tree-walker/commit/607520a00f8c084252d31e51f7ca9b7f9b4fe50a))

