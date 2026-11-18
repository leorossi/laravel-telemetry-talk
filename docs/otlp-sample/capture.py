#!/usr/bin/env python3
"""Catch one OTLP export and save it.

Stands in for Jaeger for a single request: accepts the next POST, writes the body
to the given path, and exits. Used to capture docs/otlp-sample/traces.pb.

    python3 docs/otlp-sample/capture.py /tmp/otlp.bin

Then point the app at this sink instead of Jaeger:

    docker compose -f docker-compose.yml \\
        -f docs/otlp-sample/docker-compose.otlp-dump.yml up -d app

and put it back with `docker compose up -d app`.
"""

import sys
from http.server import BaseHTTPRequestHandler, HTTPServer

OUT = sys.argv[1] if len(sys.argv) > 1 else "/tmp/otlp.bin"
PORT = int(sys.argv[2]) if len(sys.argv) > 2 else 9999


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        length = int(self.headers.get("content-length", 0))
        body = self.rfile.read(length)

        with open(OUT, "wb") as handle:
            handle.write(body)

        print(f"PATH         {self.path}")
        print(f"CONTENT-TYPE {self.headers.get('content-type')}")
        print(f"ENCODING     {self.headers.get('content-encoding')}")
        print(f"USER-AGENT   {self.headers.get('user-agent')}")
        print(f"BYTES        {length}")
        print(f"SAVED TO     {OUT}")
        print()
        print("Now look at it:")
        print(f"  xxd {OUT} | head -20")
        print(f"  strings -n 4 {OUT} | head -40")

        self.send_response(200)
        self.send_header("Content-Type", "application/x-protobuf")
        self.end_headers()
        # OTLP expects an ExportTraceServiceResponse; an empty one is valid.
        self.wfile.write(b"")

    def log_message(self, *args):
        pass


print(f"sink listening on :{PORT}, will save the next POST to {OUT}", flush=True)

server = HTTPServer(("0.0.0.0", PORT), Handler)
server.handle_request()
print("done", flush=True)
