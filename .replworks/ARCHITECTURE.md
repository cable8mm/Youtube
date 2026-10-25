# ARCHITECTURE: cable8mm/youtube

Reference sections as `ARCHITECTURE.md §N` and components as `C1`-`C18`. Terms such as Client, Shared Client, Transport, and the result types are defined in PRODUCT_SPEC.md §2 and §6.

## 1. Purpose

One entry point sits over a request pipeline. The entry point keeps a fixed public method set and delegates each call to one operation group. Operation groups build parameters. One Transport sends requests. One decoder turns response bodies into result types.

A storage layer sits beside the pipeline. An importer and a sync job are the only writers of stored records. Both reach the API only through the Shared Client.

## 2. Components

| ID | Component | Owns | Depends on |
| --- | --- | --- | --- |
| C1 | Entry Point (the Client) | Rejecting an empty API Key at construction. Creating one C2, one C3, and every C4 group. Delegating each public operation to one C4 group. The low-level helpers (PRODUCT_SPEC.md §13), including the last Page Info recorded by `decodeList`. | C2, C3, C4, C5 |
| C2 | Transport | The API Key and request settings (Referer, caching on/off, TTL). The endpoint name-to-URL table. Adding `key` to every request. Cache lookup and store through C6. Sending one GET per call. | C6, YouTube Data API |
| C3 | Response Decoder | Decoding a body. Raising C10 for an error body or a non-object body. Producing Single, Multi, List, and Paged results. Holds no state. | C10 |
| C4 | Operation Groups | One group per API resource: videos, search, channels, playlists, playlist items, activities, comment threads, categories. Each group builds request parameters and selects a result type. Shared helpers: fetch by ID (By-ID Result), fetch list (List or Paged Result), fetch normalized page (Normalized Paged Result). | C2, C3; the channels group also depends on the search group and C5 |
| C5 | URL Parser | Extracting a Video ID from a URL. Classifying a channel URL as lookup by ID, by username, by handle, or by search term. Pure functions: no requests, no settings. | none |
| C6 | Cache Wrapper | Reading and writing the host cache store with a fixed TTL. Generating cache keys. | host cache store |
| C7 | Validation Rule | Extracting a Video ID with C5 and checking existence with a Client. Mapping outcomes to validation messages. | C5, a Client (injected or the Shared Client) |
| C8 | Registration | Merging settings, offering config and migration publishing, registering the import and sync commands, building the Shared Client lazily, binding its container keys, providing the facade. | C9, C1, C13, C14 |
| C9 | Settings Definition | The API Key and sync settings and their environment mapping. | host environment |
| C10 | Error Type | The product's API exception, built from an API error object or from a message. | none |
| C11 | Records | Channel, Video, and Sync Error Records and their relations, including the optional Owner. The Owner relation helper for host models. Holds no API logic. | host database |
| C12 | Channel Input Resolver | Turning one import cell into one channels Resource Object, or a failure cause, by the rules of PRODUCT_SPEC.md §17.2. Never sends a search request. | C5, C1 |
| C13 | Channel Importer | Reading the first sheet of a spreadsheet file, resolving each `channel` cell with C12, writing Channel Records by Channel ID, and collecting row results. Stops at `quota_exceeded`. | C12, C11, C16, host spreadsheet reader |
| C14 | Sync Dispatcher | Choosing Channel Records and queuing one C15 job per record. Checks C17 first. | C11, C17, host queue |
| C15 | Channel Syncer | One Sync for one Channel Record: read the uploads playlist, look up the check set in batches of 50, write Video Records, mark unavailable videos, update the Channel Record. Converts every failure into a recorded failure. | C1, C11, C16, C17, C18 |
| C16 | Failure Classifier | Mapping an exception to a Failure Reason. Pure function: no requests, no records. | C10 |
| C17 | Quota Gate | Starting, reading, and clearing the Quota Block. | host cache store |
| C18 | Sync Notifications | The `ChannelSynced`, `ChannelSyncFailed`, and `QuotaExceeded` events. | host event dispatcher |

Host-supplied capabilities: settings storage, service container, default cache store, validator, database, queue, scheduler, event dispatcher, spreadsheet reader.

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

`Developer -> C1 -> C4 channels -> C5 classify -> C4 channels (by ID | by username | by handle) or C4 search -> C4 channels by ID`

C5 decides the lookup kind from the URL string alone, without a request. A search-term lookup sends two requests: one search, then one channel fetch by the first hit's channel ID. When the search has no hit, the second request is not sent.

### 3.3 Video URL validation

`Validator -> C7 -> C5 -> Client -> C4 videos -> C2 -> C3 -> C7 -> pass or fail message`

### 3.4 Registration

`Host boot -> C8 merges C9 -> first resolution of the Shared Client -> C1 built with the API Key setting -> reused for the rest of the process`

### 3.5 Channel import

`Administrator -> import command or host code -> C13 -> per row: C12 -> C1 channels -> C13 writes C11 -> row results`

1. C13 reads rows in order and passes each `channel` cell to C12.
2. C12 classifies the cell without a request, then sends at most one channels request through C1.
3. C13 writes the Channel Record by Channel ID, keeping existing sync state and Video Records.
4. On an API exception C13 asks C16 for the Failure Reason. On `quota_exceeded` C13 marks every remaining row skipped and stops.

### 3.6 Sync

`Host scheduler -> C14 -> C17 check -> host queue -> C15 -> C17 check -> C1 (playlist items, videos) -> C15 writes C11 -> C18 ChannelSynced`

1. C14 queues nothing during a Quota Block.
2. C15 checks C17 again when the job runs, because a block may start after queuing.
3. C15 reads uploads, then looks up new and known Video IDs together, so one pass both adds new videos and refreshes or flags known ones.

### 3.7 Sync failure

`C15 catches exception -> C16 Failure Reason -> C11 Sync Error Record + Channel Record status -> [quota_exceeded: C17 start + C18 QuotaExceeded] -> C18 ChannelSyncFailed`

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
- I8: Only C13 and C15 write records. C1-C7 never read or write records.
- I9: C12 and C15 never send a search request.
- I10: C15 never deletes a Video Record.
- I11: A failed Sync never propagates its exception out of C15. Every failure leaves one Sync Error Record.
- I12: During a Quota Block, C14 queues nothing and C15 sends nothing.

## 6. Failure boundaries

| ID | Failure | Raised by | Before a request |
| --- | --- | --- | --- |
| F1 | empty API Key at construction | C1 | yes |
| F2 | missing search criteria; empty activities channel ID | C4 | yes |
| F3 | URL rejected by video or channel parsing | C5 | yes |
| F4 | transport failure | C2, as C10 | no |
| F5 | error body or non-object body | C3, as C10 | no |
| F6 | any `Exception` during validation | C7 converts it to a fail message | n/a |
| F7 | an import cell that cannot be resolved, or an API exception on a row | C13 records a failed row and continues; stops on `quota_exceeded` | per row |
| F8 | any exception during a Sync | C15 records it (§3.7) | no |
| F9 | channel lookup returns no item during a Sync | C15 records `channel_not_found` | no |

- A response with no items is not a failure. It yields the empty value of the selected result type.
- An unbound host cache store turns caching off without a failure.
- C7 checks that the input is a string before calling C5, so no input escapes C7 as an exception.

## 7. Non-goals

- No authentication other than the API Key.
- No write operations to YouTube.
- C1-C4 do not map Resource Objects to typed data or storage. Only C13 and C15 map them into records.
- C1-C4 do not page automatically. Only C15 reads more than one page.
- No retries. The only quota handling is C17.
- No administration screen.
