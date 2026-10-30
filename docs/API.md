# API sederhana

`GET /api/projects.php?q=keyword`

Contoh respons:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Campus Event Finder",
      "slug": "campus-event-finder",
      "category": "small",
      "summary": "Aplikasi untuk menemukan event...",
      "status": "recruiting",
      "full_name": "Mahasiswa Demo",
      "program": "Informatika"
    }
  ]
}
```

Endpoint ini hanya mengembalikan proyek berstatus approved dan visibilitas campus/public.
