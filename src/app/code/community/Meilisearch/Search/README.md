# Meilisearch Search — Maho Extension

Integrates [Meilisearch](https://www.meilisearch.com/) with Maho Commerce (OpenMage/Magento 1) to provide fast, typo-tolerant search with autocomplete, instant search results, and search analytics.

## Requirements

- Maho Commerce (PHP 8.3+)
- Meilisearch server (self-hosted or Meilisearch Cloud)
- Composer

## Installation

```bash
composer require mageaus/meilisearch
```

Enable the module:

```xml
<!-- app/etc/modules/Meilisearch_Search.xml -->
<config>
    <modules>
        <Meilisearch_Search>
            <active>true</active>
            <codePool>community</codePool>
        </Meilisearch_Search>
    </modules>
</config>
```

Then flush cache and run the setup upgrade:

```bash
./maho cache:flush
./maho sys:setup:run
```

## Configuration

**System → Configuration → Meilisearch Search**

### Credentials & Setup
| Setting | Description |
|---------|-------------|
| Meilisearch Server URL | URL of your Meilisearch instance (e.g. `http://localhost:7700`) |
| Master Key | Admin API key for index management |
| Search API Key | Read-only key used in frontend (optional, recommended for production) |
| Index Name Prefix | Prefix for all index names (e.g. `prod_`) |
| Enable Indexing | Toggle product/category/page indexing |
| Enable Search | Toggle Meilisearch as the active search engine |
| Enable Logging | Log indexing errors to `var/log/` |

### Autocomplete
| Setting | Description |
|---------|-------------|
| Number of products | Max products shown in autocomplete dropdown |
| Number of categories | Max categories shown |
| Number of queries | Max suggestion queries shown |
| Min query popularity | Minimum search frequency for suggestions to appear |
| Additional Sections | Extra content sections (CMS pages, etc.) |
| Excluded Pages | Pages excluded from the search index |

### Instant Search Results Page
| Setting | Description |
|---------|-------------|
| DOM Selector | CSS selector for the search results container |
| Facets | Configurable filter attributes and their display order |
| Sorts | Available sort options on results page |
| Replace categories page | Use instant search on category pages |

## Indexes

The extension manages the following Meilisearch indexes per store:

| Index | Contents |
|-------|----------|
| `{prefix}{store}_products` | Products (enabled, visible, in-stock per store) |
| `{prefix}{store}_categories` | Categories with product counts |
| `{prefix}{store}_pages` | CMS pages |
| `{prefix}{store}_suggestions` | Popular search query suggestions |
| `{prefix}{store}_barcodes` | Product barcodes/GTINs (for POS/mobile apps) |
| `{prefix}{store}_additional_sections` | Custom additional content sections |

Each index is scoped per store — products are filtered by store assignment during indexing so indexes contain only the correct products for each store.

## Admin Menu

**System → Meilisearch Search**

| Page | Description |
|------|-------------|
| Configurations | Link to System Config |
| Manage Indexes | Index status, reindex triggers, queue controls |
| Indexing Queue | View and manage the async indexing queue |
| Queue Management | Bulk queue operations |
| Search Analytics | Click-through analytics and popular/zero-result queries |
| Reindex SKU(s) | Manually reindex specific products by SKU |

## Indexing

### Full Reindex

From the Manage Indexes admin page, or via CLI:

```bash
./maho meilisearch:reindex
```

This replaces the existing index contents using a swap approach (builds a temporary index, then swaps) to avoid downtime.

### Queue-Based Incremental Indexing

Product saves, CMS page saves, and config changes automatically queue indexing jobs. The queue runner processes jobs in the background.

### Cron Jobs

| Job | Schedule | Description |
|-----|----------|-------------|
| `meilisearch_rebuild_suggestions` | Daily at 03:00 | Rebuilds the suggestions index from `catalogsearch_query` popularity data, removing junk queries below minimum thresholds |

## Click-Through Analytics

Searches and product clicks are tracked via a lightweight beacon endpoint:

**Endpoint:** `POST /msearchtrack/ajax/trackclick`

**Payload:**
```json
{
  "query": "tennis racquet",
  "type": "product",
  "objectID": "1234",
  "name": "Wilson Pro Staff",
  "position": 2
}
```

Data is stored in the `meilisearch_search_clicks` table (created automatically on first use) and visible in **Search Analytics** in the admin.

The analytics dashboard shows:
- Top clicked queries (last 30 days)
- Top clicked products (last 30 days)
- Popular searches (all time, from `catalogsearch_query`)
- Zero-result queries
- Recent clicks (last 50)

> **Note:** The click tracking route uses `/msearchtrack/` (not `/meilisearch/`) to avoid conflicts with nginx proxy rules that forward `/meilisearch/` directly to the Meilisearch server.

## Nginx Proxy Note

If you proxy `/meilisearch/` directly to your Meilisearch server in nginx (common for the autocomplete API), the PHP controller routes under `/meilisearch/ajax/` will be intercepted. The click tracking endpoint is intentionally on a separate route (`/msearchtrack/ajax/trackclick`) to avoid this conflict.

## Observer Events

| Event | Handler | Description |
|-------|---------|-------------|
| `controller_action_layout_load_before` | `useMeilisearchSearchPopup` | Injects the Meilisearch autocomplete UI |
| `cms_page_save_commit_after` | `savePage` | Queues CMS page for re-indexing |
| `catalog_product_save_before` | `saveProduct` | Queues product for re-indexing |
| `admin_system_config_changed_section_meilisearch` | `configSaved` | Pushes updated settings to Meilisearch indexes |

## Filterable Attributes

After a full reindex, the following attributes are configured as filterable in Meilisearch by default: `categories`, `categories_without_path`, `category_ids`, `price`, plus any attributes configured in the Facets setting.

If you add new filterable attributes in config, run a full reindex to push the updated `filterableAttributes` settings to Meilisearch.

## Troubleshooting

**`Attribute X is not filterable` errors**
The index's filterable attributes don't match what the search query is filtering on. Run a full reindex to sync settings.

**Products not appearing in autocomplete**
Check that the product is enabled, visible in search, and in stock. Check the indexing queue for errors in the admin.

**Click tracking not recording**
Check that `/msearchtrack/ajax/trackclick` returns 200 in the browser network tab. The `meilisearch_search_clicks` table is created lazily on first successful request.

**Suggestions not appearing**
The suggestions index is rebuilt nightly. Run `./maho meilisearch:reindex` or trigger a rebuild from the Manage Indexes admin page.
