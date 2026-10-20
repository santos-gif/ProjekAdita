const { Pool } = require("pg");

const pool = new Pool({
  connectionString: process.env.DATABASE_URL,
  ssl: process.env.DB_SSL === "true" ? { rejectUnauthorized: false } : undefined,
  max: 10,
  idleTimeoutMillis: 30000,
  connectionTimeoutMillis: 5000
});

pool.on("error", error => {
  console.error("Kesalahan koneksi database:", error.message);
});

async function query(text, params = []) {
  return pool.query(text, params);
}

async function testConnection() {
  try {
    await pool.query("SELECT 1");
    return { ok: true };
  } catch (error) {
    console.error("Database belum terhubung:", error.message);
    return { ok: false };
  }
}

module.exports = { query, testConnection, pool };
