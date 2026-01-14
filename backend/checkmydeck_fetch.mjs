import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import { chromium } from "playwright";

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const url = "https://checkmydeck.ofdgn.com/users/76561197974617624/lists/185536";
const cachePath = path.join(__dirname, "cache", "checkmydeck.json");

const extract = (text) => {
  const data = {};
  const normalized = text.replace(/\u00a0/g, " ");

  const playableMatch =
    normalized.match(/PLAYABLE\+\s*(\d+)%/i) ||
    normalized.match(/(\d+)%\s*PLAYABLE\+/i);
  if (playableMatch) {
    data.playable_plus_percent = playableMatch[1];
  }

  const parseLine = (label) => {
    const regex = new RegExp(`${label}\\s*:\\s*(\\d+)\\s*games\\s*\\(([^)]+)\\)`, "i");
    const match = normalized.match(regex);
    if (!match) return;
    return { games: match[1], percent: match[2] };
  };

  const verified = parseLine("VERIFIED");
  if (verified) data.verified = verified;

  const playable = parseLine("PLAYABLE");
  if (playable) data.playable = playable;

  const unsupported = parseLine("UNSUPPORTED");
  if (unsupported) data.unsupported = unsupported;

  const unknown = parseLine("UNKNOWN");
  if (unknown) data.unknown = unknown;

  return data;
};

const run = async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();

  try {
    if (process.env.CF_CLEARANCE) {
      const cookie = {
        name: "cf_clearance",
        value: process.env.CF_CLEARANCE,
        domain: "checkmydeck.ofdgn.com",
        path: "/",
        httpOnly: true,
        secure: true,
      };
      await page.context().addCookies([cookie]);
    }

    await page.goto(url, { waitUntil: "domcontentloaded", timeout: 60000 });
    await page.waitForTimeout(4000);
    const title = await page.title();
    if (title && title.toLowerCase().includes("just a moment")) {
      await page.waitForTimeout(6000);
    }
    const text = await page.evaluate(() => document.body.innerText || "");
    const data = extract(text);

    const hasData = Object.keys(data).length > 0;
    const result = {
      ok: true,
      fetched_at: Math.floor(Date.now() / 1000),
      source: url,
      ...data,
    };

    if (!hasData) {
      const empty = {
        ok: false,
        error: "No data extracted (likely blocked by Cloudflare).",
        fetched_at: Math.floor(Date.now() / 1000),
        source: url,
      };
      await fs.writeFile(cachePath, JSON.stringify(empty), "utf8");
      process.stdout.write(JSON.stringify(empty));
      return;
    }

    await fs.writeFile(cachePath, JSON.stringify(result), "utf8");
    process.stdout.write(JSON.stringify(result));
  } catch (error) {
    const result = {
      ok: false,
      error: error instanceof Error ? error.message : "Unknown error",
    };
    process.stdout.write(JSON.stringify(result));
    process.exitCode = 1;
  } finally {
    await browser.close();
  }
};

run();
