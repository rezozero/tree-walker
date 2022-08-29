## 1.2.0 (2022-08-29)

### Features

* Added `StoppableDefinition` interface to prevent walker to collect children after being invoked ([b1fd429](https://github.com/rezozero/tree-walker/commit/b1fd429336d4b10bfe71498b84c494eaf6b8eee8))
* Introduced `AbstractCycleAwareWalker` to detect cyclic children collection based on `spl_object_id` ([607520a](https://github.com/rezozero/tree-walker/commit/607520a00f8c084252d31e51f7ca9b7f9b4fe50a))

