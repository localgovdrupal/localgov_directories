# LocalGov Drupal: Directories

Provides directories (channels) which have entries (pages, venues, etc.) which
can be filtered and searched.

## Facets

Facets types, and their facet values. Create types (eg. "Size"), and values (eg.
"Large", "Medium", "Small"). These can then be used on entries to filter to them.

By design the intention is that these can be excluded from configuration export,
and controlled on the site by administrators who create tyes as required.

## Directories (Channels)

A content type that defines which entries can be posted into the directory, and
which facets are enabled on it.

## Directory Entries

Entries are put into one or more directory (channel). The primary directory
defines the path, and breadcrumb to the item.

### Page

The basic directory entry. It has fields for which directory (channel), and 
what facet values (depending on the available facet types defined by the
enabled channels. It also has contact information.

### Venue

A directory entry with a location to be shown on a map. Requires the
localgov geo module.

## LocalGov Drupal Services Integration

If you have the LocalGov Services module installed, directories (channels) can
optionally be put into services. The path to the directory channel will then be
service > directory, and an entry: service > directory > entry.

## Extending directory entries

New content types can be created to go into directories by adding the
`localgov_directory_channels` and `localgov_directory_facets_select` fields.

The form widgets, and selection type, for each field ensure the correct options
for the content creator: Selector "LocalGov: Directories channels selection"
ensures only the channels that allow that content type can be posted, 
"Directories facets selection" is relatively redundant; the two widgets 
"Directory channels" and "Directory entry facets" work together to ensure the
correct directories can be chosen as primary and secondary, and that with them
the correct facets can then be selected.

New content types in directories can have which ever other fields you want
added to them.

The content type should be automatically added to the search index when you add
the directory fields. You may want to create a `directory index` display mode
as this will be used for the full text search indexing.

### Staging 'Directory facet types'

By default facet types are not exported to configuration and are treated as
content an administrator user can create on production. If you want to have
types in configuration as vocabularies would be on the site to create and
export the facet types set:
`$settings['localgov_directories_stage_site'] = TRUE;`
and it will be exported with other configuration. Any types that exist in
configuration will be imported.

## Block placement
When using a theme other than the localgov_theme, the **Directory channel search** (machine id: localgov_directories_channel_search_block) and **Directory facets** (machine id: facet_block:localgov_directories_facets) blocks should be made visible for the **Directory Channel** content type.  They can be added to a sidebar region (or equivalent) of the site theme.  Note that the facet_block:localgov_directories_facets block becomes available only after you have created at least one Directory entry content type.
