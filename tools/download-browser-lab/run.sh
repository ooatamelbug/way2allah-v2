#!/bin/sh
# Runs inside the disposable Playwright container. /work and /tmp are tmpfs (RAM): nothing large touches disk.
set -e
mkdir -p /work/media /work/dl
# Real, playable small fixtures (H.264/AAC MP4 and MP3).
ffmpeg -loglevel error -y -f lavfi -i "sine=frequency=440:duration=60" -b:a 128k /work/media/small.mp3
ffmpeg -loglevel error -y -f lavfi -i "testsrc=duration=10:size=640x360:rate=25" -f lavfi -i "sine=frequency=330:duration=10" \
  -c:v libx264 -pix_fmt yuv420p -profile:v baseline -c:a aac -b:a 96k -movflags +faststart -shortest /work/media/small.mp4
cd /lab
node server.mjs > /work/server.log 2>&1 &
for i in $(seq 1 100); do grep -q ready /work/server.log 2>/dev/null && break; sleep 0.2; done
node run.mjs "$@"
