const http = require("node:http");
const fs = require("node:fs");
const path = require("node:path");
const { URL } = require("node:url");

// Pembaca .env sederhana menggunakan modul bawaan Node.js (tanpa dotenv).
const envPath = path.join(__dirname, "..", ".env");
if (fs.existsSync(envPath)) {
  const envText = fs.readFileSync(envPath, "utf8");
  for (const line of envText.split(/\\r?\\n/)) {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith("#")) continue;
    const separator = trimmed.indexOf("=");
    if (separator < 1) continue;
    const key = trimmed.slice(0, separator).trim();
    const value = trimmed.slice(separator + 1).trim().replace(/^["']|["']$/g, "");
    if (!process.env[key]) process.env[key] = value;
  }
}

const { testConnection } = require("./database/connection");
const { listProjects, createProject, listDepartments } = require("./controllers/projectController");

const ROOT = path.join(__dirname, "..", "frontend");
const PORT = Number(process.env.PORT || 3000);

const MIME_TYPES = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".jpeg": "image/jpeg",
  ".ico": "image/x-icon"
};

function sendJson(res, statusCode, payload) {
  res.writeHead(statusCode, {
    "Content-Type": "application/json; charset=utf-8",
    "Cache-Control": "no-store",
    "X-Content-Type-Options": "nosniff"
  });
  res.end(JSON.stringify(payload));
}

function readJson(req, limit = 1024 * 1024) {
  return new Promise((resolve, reject) => {
    let body = "";
    req.on("data", chunk => {
      body += chunk;
      if (Buffer.byteLength(body) > limit) {
        reject(Object.assign(new Error("Ukuran request terlalu besar."), { statusCode: 413 }));
        req.destroy();
      }
    });
    req.on("end", () => {
      if (!body) return resolve({});
      try {
        resolve(JSON.parse(body));
      } catch {
        reject(Object.assign(new Error("Format JSON tidak valid."), { statusCode: 400 }));
      }
    });
    req.on("error", reject);
  });
}

function serveStatic(req, res, pathname) {
  const requestedPath = pathname === "/" ? "/index.html" : pathname;
  const safePath = path.normalize(requestedPath).replace(/^(\.\.[/\\])+/, "");
  const filePath = path.resolve(ROOT, "." + safePath);

  if (!filePath.startsWith(ROOT + path.sep) && filePath !== path.join(ROOT, "index.html")) {
    return sendJson(res, 403, { success: false, message: "Akses ditolak." });
  }

  fs.readFile(filePath, (error, content) => {
    if (error) {
      return sendJson(res, 404, { success: false, message: "Halaman atau file tidak ditemukan." });
    }
    const contentType = MIME_TYPES[path.extname(filePath).toLowerCase()] || "application/octet-stream";
    res.writeHead(200, {
      "Content-Type": contentType,
      "X-Content-Type-Options": "nosniff",
      "Content-Security-Policy": "default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; connect-src 'self';"
    });
    res.end(content);
  });
}

const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host || "localhost"}`);
  const pathname = url.pathname;

  try {
    if (req.method === "GET" && pathname === "/api/v1/health") {
      const db = await testConnection();
      return sendJson(res, db.ok ? 200 : 503, {
        success: db.ok,
        message: db.ok ? "Campus Collab API aktif." : "Server aktif, tetapi database belum terhubung.",
        database: db.ok ? "connected" : "disconnected"
      });
    }

    if (req.method === "GET" && pathname === "/api/v1/projects") {
      const projects = await listProjects({
        q: url.searchParams.get("q") || "",
        category: url.searchParams.get("category") || "",
        status: url.searchParams.get("status") || ""
      });
      return sendJson(res, 200, { success: true, data: projects });
    }

    if (req.method === "POST" && pathname === "/api/v1/projects") {
      const body = await readJson(req);
      const result = await createProject(body);
      if (!result.ok) return sendJson(res, 400, { success: false, message: result.message });
      return sendJson(res, 201, { success: true, message: "Pengajuan proyek berhasil disimpan.", data: result.data });
    }

    if (req.method === "GET" && pathname === "/api/v1/departments") {
      return sendJson(res, 200, { success: true, data: await listDepartments() });
    }

    if (req.method === "GET" && pathname.startsWith("/api/")) {
      return sendJson(res, 404, { success: false, message: "Endpoint API tidak ditemukan." });
    }

    if (req.method !== "GET" && req.method !== "HEAD") {
      return sendJson(res, 405, { success: false, message: "Metode tidak diizinkan." });
    }

    return serveStatic(req, res, pathname);
  } catch (error) {
    console.error(error);
    const statusCode = error.statusCode || 500;
    return sendJson(res, statusCode, {
      success: false,
      message: statusCode === 500 ? "Terjadi kesalahan pada server." : error.message
    });
  }
});

server.listen(PORT, () => {
  console.log(`Campus Collab berjalan di http://localhost:${PORT}`);
});

process.on("SIGINT", async () => {
  server.close(() => process.exit(0));
});
