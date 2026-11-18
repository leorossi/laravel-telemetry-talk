# A real OTLP payload

One captured export, kept around because a talk about telemetry should be able to show the telemetry.

`traces.pb` is the exact body of one `POST /v1/traces` from the demo app — a single request to `/fast`. It came off
the wire, it was not rebuilt.

| | |
|---|---|
| Endpoint | `POST /v1/traces` |
| Content-Type | `application/x-protobuf` |
| User-Agent | `GuzzleHttp/7` |
| Size | **3493 bytes**, uncompressed |
| Spans | **10** — `GET /fast`, `app bootstrap`, six database spans (five `SELECT`, one `INSERT`), two `view render` |

One honest detail: the request that reached the sink was the **Docker healthcheck**, not a browser — it polls `/fast`
every five seconds and got there first. Same page, same spans; the only trace of it is `user_agent.original: Wget`
and `server.address: 127.0.0.1:8000`.

## It is binary

```
$ file traces.pb
traces.pb: data

$ xxd traces.pb | head -4
00000000: 0aa2 1b0a f403 0a20 0a0c 7365 7276 6963  ....... ..servic
00000010: 652e 6e61 6d65 1210 0a0e 7465 6c65 6d65  e.name....teleme
00000020: 7472 792d 6465 6d6f 0a1b 0a09 686f 7374  try-demo....host
00000030: 2e6e 616d 6512 0e0a 0c61 3730 3631 3836  .name....a706186
```

The structure is protobuf: field tags, length prefixes, then the values. Not something to read by eye.

**But the data is in clear text.** `strings` over the same file gives you the whole trace:

```
$ strings -n 4 traces.pb | head -22
service.name
telemetry-demo
host.name
a7061860423f
host.arch
aarch64
os.description
7.0.14-linuxkit
process.runtime.name
cli-server
process.runtime.version
8.4.27
telemetry.sdk.name
opentelemetry
telemetry.sdk.version
1.15.0
laravel-opentelemetry
2.2.7
app bootstrap
SELECT
db.system.name
pgsql
```

And further down, the spans themselves:

```
view render
template.name
demo.fast
template.engine
blade
GET /fast
http.route
/fast
url.full
http://127.0.0.1:8000/fast
db.query.text
select * from "cache" where "key" in (?)
cache hit
demo:report:dashboard
```

`strings.txt` is the full output. `hexdump.txt` is the full hex dump.

## Why it matters for the talk

Two things fall out of this that are worth saying out loud:

- **Binary does not mean opaque.** You cannot read the *shape* of a payload by eye, but every attribute name and
  value is right there. Traces are self-describing data in a compact envelope.
- **One request, one POST, 3.5 KB.** Nine spans, every query, every view, every HTTP detail, shipped together when
  the request ends. That is the trade the format makes.

## In PHP it is always protobuf

OTLP defines two encodings: protobuf and JSON. The PHP SDK only ships a protobuf serializer —
`OpenTelemetry\Contrib\Otlp\SpanExporter` holds a `private ProtobufSerializer $serializer`, and `ProtobufSerializer`
is the only serializer in the `exporter-otlp` package. `ContentTypes` declares `application/json`, but nothing
implements it.

Forcing `OTEL_EXPORTER_OTLP_PROTOCOL=http/json` changes nothing: the wire still carries
`application/x-protobuf`. Other language SDKs can emit JSON; this one cannot.

## Capturing another one

Two commands. First a sink on the host that keeps the next POST it receives:

```bash
python3 docs/otlp-sample/capture.py /tmp/otlp.bin
```

Then point the app at it instead of Jaeger, hit something, and skip past the compose file's own endpoint variable:

```bash
docker compose -f docker-compose.yml -f docs/otlp-sample/docker-compose.otlp-dump.yml up -d app
curl -s -o /dev/null http://localhost:8000/fast
```

The sink prints the request line, the content type and the byte count, and writes the body to the file it was given.
Put the app back afterwards:

```bash
docker compose up -d app
```

Then read it:

```bash
xxd /tmp/otlp.bin | head -20
strings -n 4 /tmp/otlp.bin | head -40
```

The endpoint has to be overridden in the compose file rather than in `.env`, because `docker-compose.yml` sets
`OTEL_EXPORTER_OTLP_ENDPOINT` as a real environment variable and real environment variables beat `.env`.
