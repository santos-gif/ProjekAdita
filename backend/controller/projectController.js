const { query } = require("../database/connection");

const ALLOWED_CATEGORIES = new Set([
  "Teknologi", "Bisnis", "Desain", "Sosial", "Penelitian", "Lainnya"
]);

async function listProjects(filters = {}) {
  const conditions = [];
  const params = [];

  if (filters.q) {
    params.push(`%${filters.q.trim()}%`);
    conditions.push(`(p.title ILIKE $${params.length} OR p.description ILIKE $${params.length})`);
  }
  if (filters.category) {
    params.push(filters.category);
    conditions.push(`p.category = $${params.length}`);
  }
  if (filters.status) {
    params.push(filters.status);
    conditions.push(`p.status = $${params.length}`);
  }

  const where = conditions.length ? `WHERE ${conditions.join(" AND ")}` : "";
  const result = await query(
    `SELECT p.id, p.title, p.description, p.category, p.status, p.max_members,
            p.created_at, d.name AS department_name
       FROM projects p
       LEFT JOIN departments d ON d.id = p.department_id
       ${where}
       ORDER BY p.created_at DESC
       LIMIT 100`,
    params
  );
  return result.rows;
}

async function createProject(body) {
  const title = typeof body.title === "string" ? body.title.trim() : "";
  const description = typeof body.description === "string" ? body.description.trim() : "";
  const category = typeof body.category === "string" ? body.category : "";
  const departmentId = body.departmentId ? Number(body.departmentId) : null;
  const maxMembers = body.maxMembers === undefined ? 5 : Number(body.maxMembers);

  if (title.length < 4 || title.length > 120) {
    return { ok: false, message: "Judul harus terdiri dari 4–120 karakter." };
  }
  if (description.length < 20 || description.length > 3000) {
    return { ok: false, message: "Deskripsi harus terdiri dari 20–3000 karakter." };
  }
  if (!ALLOWED_CATEGORIES.has(category)) {
    return { ok: false, message: "Kategori proyek tidak valid." };
  }
  if (!Number.isInteger(maxMembers) || maxMembers < 1 || maxMembers > 100) {
    return { ok: false, message: "Jumlah anggota harus berupa angka 1–100." };
  }
  if (departmentId !== null && (!Number.isInteger(departmentId) || departmentId < 1)) {
    return { ok: false, message: "Program studi tidak valid." };
  }

  if (departmentId !== null) {
    const department = await query("SELECT id FROM departments WHERE id = $1", [departmentId]);
    if (!department.rowCount) return { ok: false, message: "Program studi tidak ditemukan." };
  }

  const result = await query(
    `INSERT INTO projects (title, description, category, department_id, max_members, status)
     VALUES ($1, $2, $3, $4, $5, 'pending')
     RETURNING id, title, description, category, status, max_members, created_at`,
    [title, description, category, departmentId, maxMembers]
  );
  return { ok: true, data: result.rows[0] };
}

async function listDepartments() {
  const result = await query("SELECT id, name, code FROM departments ORDER BY name");
  return result.rows;
}

module.exports = { listProjects, createProject, listDepartments };
