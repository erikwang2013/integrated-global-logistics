// Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
// 运行: node example.js （可用 TRACKING_API_KEY / TRACKING_BASE_URL 环境变量覆盖默认值）
const { TrackingClient, TrackingApiError } = require("./tracking-client.js");

const client = new TrackingClient(
  process.env.TRACKING_API_KEY || "demo-api-key",
  process.env.TRACKING_BASE_URL || "http://localhost:8080"
);

async function run(name, fn) {
  try {
    console.log(`${name}:`, JSON.stringify(await fn()));
  } catch (e) {
    console.log(`${name} failed: code=${e.code} message=${e.message} errorCode=${e.errorCode}`);
  }
}

async function main() {
  await run("carriers", () => client.listCarriers());
  await run("query_tracking", () => client.queryTracking("LX123456789CN"));
  await run("get_tracking", () => client.getTracking("demo-query"));
  await run("subscribe", () => client.subscribe("china-post", "https://example.com/cb"));
}

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
