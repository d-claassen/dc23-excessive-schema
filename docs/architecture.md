# dc23-excessive-schema — Architecture

## Purpose

`dc23-excessive-schema` fills the gaps Yoast SEO leaves around non-`Article` schema:
linking between main entities via `mentions`, supporting non-`Article` post types in
schema, and (later) providing a UI for type and subtype display/selection on any post type.

It does this by acting as a thin coordination layer over existing schema producers.
It does **not** generate schema for things other plugins already handle.

## Model: source-derived with overrides (Model C)

Three models were considered:

A. **Read source data only**. minimal, but blocks any field the source plugin
  doesn’t track.
B. **Own all schema data**. maximum control, massive duplication, drift inevitable.
C. **Source-derived with user overrides**. read what source plugins already
  produce, allow Yoast-sidebar overrides on top, add fields the source plugin
  doesn’t track.

This plugin uses **Model C**. It mirrors how Yoast already works for SEO meta
(custom title overrides post title, falls back to the source).

For schema specifically, the override layer is deferred. Source plugins are
trusted to produce correct schema and stable `@id`s. If a source plugin’s output
is broken, the corresponding glue plugin (`dc23-tea`, `dc23-software-downloads`,
etc.) is responsible for fixing it — not this plugin.

## Responsibilities

### `dc23-excessive-schema` owns

- The schema.org type tree and subtype navigation.
- The adapter registry and its registration mechanism.
- The `mentions` injection logic across all registered main entity types.
- (Future) the Yoast-sidebar UI for type and subtype selection.
- The merge logic between adapter-provided data and any future overrides.

### `dc23-excessive-schema` does not own

- Knowledge of WooCommerce, EDD, TEC, or any source plugin.
- Schema field derivation. Source plugins (or their glue adapters) handle this.
- Storage of source-plugin-native data.

When code in this plugin checks for a specific source plugin’s presence or
behaviour, that’s a signal the logic belongs in an adapter instead.

## Adapters

Adapters are thin shims that connect a post type to its existing schema output.
They live in domain plugins (`dc23-tea` for TEC, `dc23-software-downloads` for EDD,
etc.), not here.

### Adapter contract

Adapters provide two things:

1. **Type identity** — what root schema.org type this post type maps to.
1. **Entity ID resolution** — how to find the `@id` of the existing main entity
   node so `mentions` can link to it.

Of course this can be extended with any needed methods to support more 
extendive schema solutions. If so, this will always be done eith backwards
compatibility in mind.

```php
interface Schema_Adapter {
    public function get_root_type(): string;
    public function get_entity_id( Indexable $indexable ): string;
}
```

### Default implementation accepts config

For the common case where the adapter is purely declarative, a default
implementation accepts a config array. Adapters with non-trivial logic (e.g.
recurring events with different `@id` strategies for instances vs parent) can
implement the interface directly.

```php
// Simple case — config array, internally wrapped in Default_Schema_Adapter
dc_schema_register_adapter( 'tribe_events', [
    'root_type' => 'Event',
    'entity_id' => fn( $indexable ) => $indexable->permalink . '#event',
] );

// Complex case — full class implementation
dc_schema_register_adapter( 'tribe_events', new Recurring_Event_Adapter() );
```

This mirrors WP core’s pattern for `register_block_type()`, which accepts
either a config array or a `WP_Block_Type` instance.

## Registration

Adapters register on a documented action hook fired by this plugin during
`init`. This avoids load-order coupling: domain plugins can `add_action`
unconditionally at file load, and registration only fires if this plugin is
active.

```php
// In dc-events
add_action( 'dc23_schema_register_adapters', function() {
    dc23_schema_register_adapter( 'tribe_events', [
        'root_type' => 'Event',
        'entity_id' => fn( $i ) => $i->permalink . '#event',
    ] );
} );
```

If `dc23-excessive-schema` is inactive, the action never fires and the adapter
is a no-op. Domain plugins continue to function.

## Storage and accessors

Registered adapters are stored in a container owned by this plugin. Consumers
(internal and external) read via accessor functions, never the storage
directly. This matches WP core conventions (`get_post_type_object()`,
`get_post_types()`).

```php
dc23_schema_get_adapter( 'tribe_events' );    // single lookup
dc23_schema_get_adapters();                    // enumeration
dc23_schema_adapter_exists( 'tribe_events' );  // boolean
```

Enumeration is needed for the future sidebar UI; lookup is needed for
`mentions` resolution at schema-build time.

## Hybrid types

Schema.org permits multi-typing via JSON-LD `@type` arrays
(`{ "@type": ["Product", "Book"] }`). The output layer treats `@type` as an
array internally, even when only one value is present, so hybrid types become
a population change later rather than a structural change.

No hybrid type UI or registration is built initially. It’s deferred until a
concrete need exists.

## Type tree ownership

Schema.org subtypes are a closed vocabulary. This plugin ships the relevant
slice of the tree. Adapters declare a `root_type`. They do not invent new types.

## Override precedence

When the override layer is added later, the precedence rule is: **user
override wins when present, adapter-derived value otherwise**. Empty fields
in the sidebar fall back to the adapter. This matches Yoast’s existing UX
pattern for SEO title and description.

Per-field configurable precedence is not built initially.

## Soft dependency convention

Domain plugins (`dc23-tea`, `dc23-software-downloads`, future adapters) do **not**
hard-depend on `dc23-excessive-schema`. They register via `add_action` and
silently no-op when this plugin is inactive. They retain their other
responsibilities (TEC integration, EDD integration, etc.) independently.

## Roadmap (informative, not normative)

The first concrete consumers are:

1. **`dc23-tea`** — TEC adapter. Registers `tribe_events` as `Event`. First
   stress test of the mechanism end-to-end.
1. **`dc23-software-downloads`** — EDD adapter. Registers downloads as
   `SoftwareApplication`. Deliberately different shape from Events to
   stress-test the abstraction.

After two adapters work without special-casing, the sidebar UI design has
empirical input to work from. Not before.