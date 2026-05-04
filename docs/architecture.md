# dc23-excessive-schema — Architecture

## Purpose

`dc23-excessive-schema` fills the gaps Yoast SEO leaves around non-`Article` schema:
linking between main entities via `mentions`, supporting non-`Article` post types in
schema, and (later) providing a UI for type and subtype selection on any post type.

It does this by acting as a thin coordination layer over existing schema producers.
It does **not** generate schema for things other plugins already handle.

## Model: source-derived with overrides (Model C)

Three models were considered:

- **A. Read source data only**. minimal, but blocks any field the source plugin
  doesn’t track.
- **B. Own all schema data**. maximum control, massive duplication, drift inevitable.
- **C. Source-derived with user overrides**. read what source plugins already
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
- The `dc23_schema_main_entity` filter where all enrichment logic attaches.
- The `mentions` injection logic across all registered main entity types.
- (Future) the Yoast-sidebar UI for type and subtype selection.
- (Future) the merge logic between adapter-provided data and user overrides.

### `dc23-excessive-schema` does not own

- Knowledge of WooCommerce, EDD, TEC, or any source plugin.
- Schema field derivation. Source plugins (or their glue adapters) handle this.
- The bridging from a source plugin’s filter into the uniform mutation point.
  That’s the adapter’s job.
- Storage of source-plugin-native data.

When code in this plugin checks for a specific source plugin’s presence or
behaviour, that’s a signal the logic belongs in an adapter instead.

## Adapters

Adapters are thin shims that connect a post type to its existing schema output.
They live in glue plugins (`dc23-tea` for TEC, `dc23-software-downloads` for
EDD, etc.), not here.

### Adapter contract

Adapters provide four things:

1. **Type identity** — what root schema.org type this post type maps to.
1. **Entity ID resolution** — how to find the `@id` of the existing main
   entity node so `mentions` and other references can link to it.
1. **Optional subtype constraint** — which subtypes of the root type are
   valid for this post type (closed taxonomy, configurable subset). `null`
   means no constraint.
1. **Enrichment setup** — installs the adapter’s bridge from the source
   plugin’s filter into `dc23_schema_main_entity`. Called automatically by
   the registration function. Adapters with no bridging needs (rare) can
   implement it as a no-op.

```php
namespace DC23\ExcessiveSchema;

interface Main_Entity_Adapter {
    public function get_root_type(): string;
    public function get_entity_id( Indexable $indexable ): string;
    public function get_allowed_subtypes(): ?array;
    public function setup_main_entity_enrichment(): void;
}
```

The setup method is imperative — it hooks the source plugin’s filter and
wires up the bridge described below. The interface enforces that this happens,
without prescribing *how* (because filter signatures vary by source plugin).

### Default implementation accepts config

For the common case where the adapter is purely declarative, a default
implementation accepts a config array. Adapters with non-trivial logic (e.g.
recurring events with different `@id` strategies for instances vs parent) can
implement the interface directly.

```php
// Simple case — config array, internally wrapped in Default_Schema_Adapter
dc23_schema_register_adapter( 'tribe_events', [
    'root_type' => 'Event',
    'entity_id' => fn( $indexable ) => $indexable->permalink . '#event',
] );

// Complex case — full class implementation
dc23_schema_register_adapter( 'tribe_events', new Recurring_Event_Adapter() );
```

This mirrors WP core’s pattern for `register_block_type()`, which accepts
either a config array or a `WP_Block_Type` instance.

## Bridging

Each adapter is responsible for bridging its source plugin’s schema filter
into `dc23_schema_main_entity`, the uniform mutation point this plugin owns.

The adapter knows its source plugin’s filter signature intimately and hooks it
in plain code — no DSL, no abstraction over filter shapes. The bridge
normalises the source plugin’s data shape to an associative array, fires
`dc23_schema_main_entity` with the indexable, and returns the result in the
shape the source plugin expects.

```php
namespace DC23\Tea;

use DC23\ExcessiveSchema\Main_Entity_Adapter;

class TEC_Adapter implements Main_Entity_Adapter {
    public function get_root_type(): string {
        return 'Event';
    }

    public function get_entity_id( Indexable $indexable ): string {
        return $indexable->permalink . '#event';
    }

    public function get_allowed_subtypes(): ?array {
        return null;
    }

    public function setup_main_entity_enrichment(): void {
        add_filter( 'tribe_json_ld_event_object', [ $this, 'enrich' ], 10, 3 );
    }

    public function enrich( $data, $args, $post ) {
        $indexable = YoastSEO()->meta->for_post( $post->ID )->indexable;
        $array     = (array) $data;
        $array     = apply_filters( 'dc23_schema_main_entity', $array, $indexable );
        return (object) $array;
    }
}
```

Each source plugin gets a similarly small bridge in its own glue plugin:

- `dc23-tea` bridges `tribe_json_ld_event_object` (object input, three args).
- `dc23-software-downloads` bridges whatever filter it exposes around EDD.
- A built-in adapter for Yoast’s Article bridges `wpseo_schema_article`
  (array input, two args). Lives in this plugin as a reference implementation.

Common patterns may emerge across these bridges. Helpers will be extracted
once a real pattern crystallises across multiple adapters, not in advance.

## Registration

Adapters register on a documented action hook fired by this plugin during
`init`. This avoids load-order coupling: glue plugins can `add_action`
unconditionally at file load, and registration only fires if this plugin is
active.

```php
// In dc23-tea
add_action( 'dc23_schema_register_adapters', function() {
    dc23_schema_register_adapter( 'tribe_events', new TEC_Adapter() );
} );
```

`dc23_schema_register_adapter()` calls the adapter’s
`setup_main_entity_enrichment()` method automatically after registering it.
The bridge is installed as a side-effect of registration; consumers can’t
forget to wire it up.
If `dc23-excessive-schema` is inactive, the action never fires, the adapter
isn’t registered, and its bridging never installs. The glue plugin continues
to function.

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

## The `dc23_schema_main_entity` filter

All enrichment logic attaches to this filter, owned by this plugin:

```php
apply_filters( 'dc23_schema_main_entity', array $entity, Indexable $indexable );
```

Adapters fire it from their bridges. This plugin hooks it for `mentions`
injection now and (later) override application, subtype mutation, and
sidebar-driven field changes.

## Hybrid types

Schema.org permits multi-typing via JSON-LD `@type` arrays
(`{ "@type": ["Product", "Book"] }`). The output layer treats `@type` as an
array internally, even when only one value is present, so hybrid types become
a population change later rather than a structural change.

No hybrid type UI or registration is built initially. It’s deferred until a
concrete need exists.

## Type tree ownership

Schema.org subtypes are a closed vocabulary. This plugin ships the relevant
slice of the tree. Adapters declare a `root_type` and (optionally) constrain
which descendants are offered in the UI. They do not invent new subtypes.

## Override precedence

When the override layer is added later, the precedence rule is: **user
override wins when present, adapter-derived value otherwise**. Empty fields
in the sidebar fall back to the adapter. This matches Yoast’s existing UX
pattern for SEO title and description.

Per-field configurable precedence is not built initially.

## Soft dependency convention

Glue plugins (`dc23-tea`, `dc23-software-downloads`, future adapters) do **not**
hard-depend on `dc23-excessive-schema`. They register via `add_action` and
silently no-op when this plugin is inactive. They retain their other
responsibilities (TEC integration, EDD integration, etc.) independently.

## Roadmap (informative, not normative)

The first concrete consumers are:

1. **`dc23-tea`** — TEC adapter. Registers `tribe_events` as `Event`. First
   stress test of the mechanism end-to-end.
1. **`dc23-software-downloads`** — EDD adapter. Registers downloads as
   `SoftwareApplication`. Deliberately different shape from Events to
   stress-test the abstraction (no native filter; glue plugin must create one).

After two adapters work without special-casing, the sidebar UI design has
empirical input to work from. Not before.