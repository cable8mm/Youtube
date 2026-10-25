# ARCHITECTURE: cable8mm/youtube

Reference sections as `ARCHITECTURE.md §N` and components as `C1`-`C10`. Terms such as Client, Shared Client, Transport, and the result types are defined in PRODUCT_SPEC.md §2 and §6.

## 1. Purpose

One entry point sits over a request pipeline. The entry point keeps a fixed public method set and delegates each call to one operation group. Operation groups build parameters. One Transport sends requests. One decoder turns response bodies into result types.

## 2. Components

| ID | Component | Owns | Depends on |
| --- | --- | --- | --- |
| C1 | Entry Point (the Client) | Rejecting an empty API Key at construction. Creating one C2, one C3, and every C4 group. Delegating each public operation to one C4 group. The low-level helpers (PRODUCT_SPEC.md §13), including the last Page Info recorded by `decodeList`. | C2, C3, C4, C5 |
| C2 | Transport | The API Key and request settings (Referer, caching on/off, TTL). The endpoint name-to-URL table. Adding `key` to every request. Cache lookup and store through C6. Sending one GET per call. | C6, YouTube Data API |
| C3 | Response Decoder | Decoding a body. Raising C10 for an error body or a non-object body. Producing Single, Multi, List, and Paged results. Holds no state. | C10 |
| C4 | Operation Groups | One group per API resource: videos, search, channels, playlists, playlist items, activities, comment threads, categories. Each group builds request parameters and selects a result type. Shared helpers: fetch by ID (By-ID Result), fetch list (List or Paged Result), fetch normalized page (Normalized Paged Result). | C2, C3; the channels group also depends on the search group and C5 |
| C5 | URL Parser | Extracting a Video ID from a URL. Classifying a channel URL as lookup by ID, by username, or by search term. Pure functions: no requests, no settings. | none |
| C6 | Cache Wrapper | Reading and writing the host cache store with a fixed TTL. Generating cache keys. | host cache store |
| C7 | Validation Rule | Extracting a Video ID with C5 and checking existence with a Client. Mapping outcomes to validation messages. | C5, a Client (injected or the Shared Client) |
| C8 | Registration | Merging settings, offering config publishing, building the Shared Client lazily, binding its container keys, providing the facade. | C9, C1 |
| C9 | Settings Definition | The API Key setting and its environment mapping. | host environment |
| C10 | Error Type | The product's API exception, built from an API error object or from a message. | none |

Host-supplied capabilities: settings storage, service container, default cache store, validator.

External boundary (AGENTS.md EXTERNAL_BOUNDARY): the YouTube Data API v3. Only C2 crosses it.

## 3. Data flows

### 3.1 Operation call

`Developer -> C1 -> C4 -> C2 -> [C6 hit] or [YouTube Data API -> C6 store] -> C3 -> C4 -> C1 -> result`

1. C1 passes the call to its C4 group unchanged.
2. C4 validates local arguments (§6 F2), builds parameters, and names the endpoint.
3. C2 adds `key`. When caching applies, C2 returns a cached body without sending.
4. Otherwise C2 sends the request and stores the body when it is cacheable (§4).
5. C3 decodes the body into the result type C4 selected.

### 3.2 Channel URL resolution

`Developer -> C1 -> C4 channels -> C5 classify -> C4 channels (by ID | by username) or C4 search -> C4 channels by ID`

C5 decides the lookup kind from the URL string alone, without a request. A search-term lookup sends two requests: one search, then one channel fetch by the first hit's channel ID. When the search has no hit, the second request is not sent.

### 3.3 Video URL validation

`Validator -> C7 -> C5 -> Client -> C4 videos -> C2 -> C3 -> C7 -> pass or fail message`

### 3.4 Registration

`Host boot -> C8 merges C9 -> first resolution of the Shared Client -> C1 built with the API Key setting -> reused for the rest of the process`

## 4. Caching

- C2 uses C6 only when caching is on for that Client and the host cache store is bound.
- The cache key covers the endpoint URL and every sent parameter, including `key`, in name order.
- C2 stores a body only when it decodes to an object with no `error` member.
- C6 keeps no state between calls other than its TTL.

## 5. Invariants

- I1: Every request carries `key`.
- I2: All C4 groups of one Client share that Client's single C2 and C3. A setting changed on the Client applies to all groups at once.
- I3: C3 never keeps Page Info between calls. Page Info is returned inside the result. Only C1's `decodeList` helper records the last Page Info.
- I4: C5 sends no requests and reads no settings.
- I5: Resource Objects pass from C3 to the caller unchanged.
- I6: C2 reads the API Key and settings when it sends a request, not when C4 groups are created.
- I7: Only C2 communicates with the YouTube Data API.

## 6. Failure boundaries

| ID | Failure | Raised by | Before a request |
| --- | --- | --- | --- |
| F1 | empty API Key at construction | C1 | yes |
| F2 | missing search criteria; empty activities channel ID | C4 | yes |
| F3 | URL rejected by video or channel parsing | C5 | yes |
| F4 | transport failure | C2, as C10 | no |
| F5 | error body or non-object body | C3, as C10 | no |
| F6 | any `Exception` during validation | C7 converts it to a fail message | n/a |

- A response with no items is not a failure. It yields the empty value of the selected result type.
- An unbound host cache store turns caching off without a failure.
- C7 does not catch `TypeError`. A non-string input that cannot be coerced to a string escapes C7 (PRODUCT_SPEC.md §11.1).

## 7. Non-goals

- No authentication other than the API Key.
- No write operations.
- No mapping of Resource Objects to typed data or storage.
- No automatic paging, retries, or quota accounting.
