USE kampus_kolaborasi;
INSERT IGNORE INTO study_programs (id,name,focus_areas) VALUES
(1,'Informatika',JSON_ARRAY('Application Development','Artificial Intelligence (AI)','Cyber Security')),
(2,'Sistem Informasi',JSON_ARRAY('Business Intelligence','Information System Governance','Enterprise System')),
(3,'Desain Komunikasi Visual',JSON_ARRAY('Visual Interactive','Visual Creative')),
(4,'Arsitektur',JSON_ARRAY('Perancangan bangunan','Pengembangan kota modern')),
(5,'Desain Interior',JSON_ARRAY('Penataan ruang','Desain komersial dan hunian')),
(6,'Perencanaan Wilayah dan Kota',JSON_ARRAY('Estate Management','Urban Design')),
(7,'Teknik Sipil',JSON_ARRAY('Infrastruktur','Pembangunan kawasan terpadu')),
(8,'Manajemen',JSON_ARRAY('Digital Business','Creativepreneurship','Township Business')),
(9,'Akuntansi',JSON_ARRAY('Praktik akuntansi','Audit berbasis industri')),
(10,'Pariwisata',JSON_ARRAY('Manajemen pariwisata','Hospitality dan perhotelan')),
(11,'Seni Kuliner',JSON_ARRAY('Pengembangan produk kuliner','Inovasi makanan dan minuman','Proyek kuliner dan industri hospitality'));

-- Password demo: Password123!
INSERT IGNORE INTO users (id,full_name,email,password_hash,role,nim,semester,study_program_id,bio)
VALUES
(1,'Administrator Kampus','admin@kampus.test','$2y$12$3QYYvw8xaGOJYGKZjfsq1Ozk9Q4D9UPeyR0pIk6dOamGKLom3to3W','admin',NULL,NULL,NULL,'Administrator demo platform.'),
(2,'Mahasiswa Demo','mahasiswa@kampus.test','$2y$12$3QYYvw8xaGOJYGKZjfsq1Ozk9Q4D9UPeyR0pIk6dOamGKLom3to3W','user','2510101001',3,1,'Tertarik pada pengembangan aplikasi dan AI.');

INSERT IGNORE INTO projects (id,owner_id,title,slug,category,summary,description,objectives,expected_output,required_skills,members_needed,status,submission_status,visibility)
VALUES
(1,2,'Campus Event Finder','campus-event-finder','small','Aplikasi untuk menemukan event dan kompetisi mahasiswa.','Membangun aplikasi web yang mengumpulkan informasi event, kompetisi, dan kegiatan kampus dalam satu tempat.','Mempermudah mahasiswa menemukan kegiatan yang relevan.','Prototype web responsif.','["PHP","HTML","CSS","JavaScript"]',2,'recruiting','approved','campus');
INSERT IGNORE INTO project_members (project_id,user_id,member_role) VALUES (1,2,'Project Leader');
