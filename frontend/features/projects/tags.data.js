/**
 * Master List Tag Resmi ProjekAdita
 * Berdasarkan Program Studi & Bidang Keilmuan Universitas Pradita
 */
export const TAG_CATEGORIES = {
  'Management': [
    'Digital Business',
    'Township Business',
    'Creativepreneurship',
    'Digital Marketing',
    'Brand Management',
    'Business Strategy',
    'Entrepreneurship',
    'Business Analytics',
    'Content Creation',
    'Investment Analysis',
    'Real Estate Development',
    'Financial Management'
  ],
  'Information Technology': [
    'Artificial Intelligence',
    'Cyber Security',
    'Big Data & IoT Apps Development',
    'Machine Learning',
    'Deep Learning',
    'Data Science',
    'Data Engineering',
    'Natural Language Processing',
    'Computer Vision',
    'Digital Forensics',
    'Penetration Testing',
    'Network Security',
    'Software Engineering',
    'Web Development',
    'Mobile App Development',
    'UI/UX Design',
    'Human-Computer Interaction',
    'Internet of Things (IoT)',
    'Embedded Systems'
  ],
  'Culinary Arts': [
    'Culinary Arts',
    'Patisserie Arts',
    'Indonesian Cuisine',
    'Asian Cuisine',
    'Western Cuisine',
    'Food Innovation',
    'Food Styling',
    'Food Presentation',
    'Pastry & Bakery',
    'Kitchen Operations',
    'Food Entrepreneurship',
    'Recipe Development'
  ],
  'Accounting': [
    'Auditing',
    'Taxation',
    'Digital Accounting',
    'Financial Accounting',
    'Financial Reporting',
    'Financial Analysis',
    'Internal Controls',
    'Risk Management',
    'Accounting Information Systems',
    'Enterprise Resource Planning (ERP)',
    'Business Process Auditing',
    'Tax Compliance'
  ],
  'Visual Communication Design': [
    'Visual Creative',
    'Visual Interactive',
    'Graphic Design',
    'Visual Storytelling',
    'Branding & Visual Identity',
    'Advertising & Campaign Design',
    'Digital Illustration',
    'Video Production',
    'Animation & Motion Graphics',
    '3D Animation',
    'Game Design',
    'Character Design',
    'Packaging Design',
    'Digital Media',
    'Visual Merchandising',
    'Photography'
  ],
  'Architecture': [
    'Property Development',
    'Technology Innovation',
    'Architectural Design',
    'Computational Design',
    'Sustainable Architecture',
    'Universal Design',
    'Contemporary Architecture',
    'Site Planning',
    'Building Technology',
    'Smart Building Systems',
    'Construction Management',
    'Building Economics',
    'Architectural Visualization',
    'Building Information Modeling (BIM)'
  ],
  'Business Information System': [
    'Information System Governance',
    'Enterprise System',
    'Business Intelligence',
    'Business Analytics',
    'Data Analytics & Visualization',
    'Systems Analysis',
    'Information System Development',
    'IT Audit',
    'ERP Implementation',
    'Business Process Management',
    'Decision Support Systems',
    'Information Security Management',
    'Database Management'
  ],
  'Civil Engineering': [
    'Structural Engineering',
    'Quantity Surveyor & Project Cost Control',
    'Construction Engineering Management',
    'Construction Materials',
    'Structural Analysis',
    'Infrastructure Development',
    'Transportation Engineering',
    'Water Resources Engineering',
    'Sanitation & Drainage',
    'Construction Scheduling',
    'Construction Quality Management',
    'Geotechnical Engineering',
    'Construction Cost Estimation'
  ],
  'Hospitality & Tourism': [
    'Tourism & Events',
    'Hotel Operations',
    'Culinary Business',
    'Tourism Management',
    'Event Management',
    'Destination Development',
    'Hotel Management',
    'Front Office Operations',
    'Housekeeping',
    'Food & Beverage Service',
    'Food Cost Control',
    'Sustainable Tourism',
    'Travel Experience Design',
    'Hospitality Entrepreneurship'
  ],
  'Interior Design': [
    'Residential',
    'Hospitality & Commercial Space',
    'Interior Space Planning',
    'Furniture Design',
    'Lighting Design',
    'Retail Design',
    'Office Design',
    'Hospitality Interior Design',
    'Exhibition & Gallery Design',
    'Universal & Inclusive Design',
    'Smart Living',
    'Sustainable Interior Design',
    'Material & Finishing Design',
    'Interior Visualization'
  ],
  'Urban Planning': [
    'Urban Design',
    'Estate Management',
    'Smart City',
    'Sustainable Urban Development',
    'Land Use Planning',
    'Housing & Settlement Planning',
    'Urban Mobility',
    'Transportation Planning',
    'Public Space Design',
    'Community Development',
    'Property Market Analysis',
    'Urban Infrastructure',
    'Environmental Planning',
    'Regional Development'
  ]
};

// Flatten daftar seluruh tag unik (tanpa duplikat nama)
const uniqueTagsMap = new Map();

for (const [category, tags] of Object.entries(TAG_CATEGORIES)) {
  for (const tag of tags) {
    const key = tag.trim().toLowerCase();
    if (!uniqueTagsMap.has(key)) {
      uniqueTagsMap.set(key, { name: tag.trim(), category });
    }
  }
}

export const ALL_MASTER_TAGS = Array.from(uniqueTagsMap.values());
