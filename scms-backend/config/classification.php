<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Issue Classification Taxonomy
    |--------------------------------------------------------------------------
    |
    | Defines the standard AI issue categories and subcategories for SCMS.
    | This is separate from organizational routing (academic_unit vs operational_dept).
    |
    */
    'categories' => [
        'IT and Technology' => [
            'Computer Hardware' => ['computer', 'pc', 'desktop', 'laptop', 'monitor', 'computer monitor', 'broken monitor', 'keyboard', 'mouse', 'hardware', 'motherboard', 'ram', 'boot', 'shut down', 'powers off', 'blue screen', 'bsod'],
            'Software Problems' => ['software', 'os', 'operating system', 'windows', 'linux', 'application', 'app', 'crash', 'freeze', 'bug', 'license', 'install'],
            'Virus / Malware' => ['virus', 'malware', 'computer malware', 'computer virus', 'trojan', 'ransomware', 'infected pc', 'infected computer', 'malware infection', 'virus infection', 'phishing', 'hacked', 'suspicious file', 'worm', 'spyware'],
            'Network / Internet' => ['wifi', 'wi-fi', 'internet', 'network', 'connection', 'disconnected', 'slow internet', 'lan', 'ethernet', 'dns', 'ip address', 'router', 'no internet', 'internet unavailable', 'classroom internet'],
            'Account / Login Issues' => ['login', 'account', 'password', 'credentials', 'locked out', 'portal account', 'sign in', 'authenticate', 'reset password'],
            'Printer / Peripheral Problems' => ['printer', 'scanner', 'photocopier', 'printing', 'toner', 'paper jam', 'peripheral', 'barcode scanner', 'not printing'],
            'System Access' => ['portal', 'lms', 'canvas', 'moodle', 'grading system', 'enrollment portal', 'access denied', '403 forbidden', 'system down'],
            'Other IT Issues' => ['it support', 'tech support', 'server', 'backup', 'data'],
        ],
        'Academic Concerns' => [
            'Classroom Equipment / AV' => [
                'projector', 'classroom projector', 'lab projector', 'laboratory projector', 'hdmi', 'hdmi cable', 'display cable',
                'projector display', 'projector screen', 'audio visual', 'av', 'speaker in classroom', 'sound system', 'smart board',
                'microphone', 'mic', 'flickering projector', 'projector flickering', 'projector broken', 'no display'
            ],
            'Classroom Issues' => ['classroom assignment', 'room assignment', 'room conflict', 'aircon in classroom', 'whiteboard', 'desk in room', 'chair in classroom'],
            'Laboratory Issues' => ['lab manual', 'experiment', 'lab equipment', 'science lab', 'chemistry lab', 'biology lab', 'physics lab', 'chemical', 'specimen'],
            'Instructor / Teaching Concerns' => ['instructor', 'professor', 'faculty', 'teacher', 'lecture', 'teaching', 'grading', 'syllabus', 'consultation'],
            'Learning Resources' => ['textbook', 'library', 'modules', 'reference material', 'study guide', 'online resource'],
            'Academic Facilities' => ['study hall', 'reading room', 'library facility', 'discussion room'],
            'Other Academic Concerns' => ['curriculum', 'prerequisite', 'overload', 'graduation requirement'],
        ],
        'Non-Academic Concerns' => [
            'Administrative Services' => ['admin', 'clearance', 'document request', 'certificate', 'endorsement'],
            'Registrar Services' => ['registrar', 'transcript', 'tor', 'diploma', 'form 137', 'cor', 'certificate of registration', 'grades copy'],
            'Cashier / Payment' => ['cashier', 'tuition', 'payment', 'receipt', 'or', 'refund', 'installment', 'balance', 'accounting'],
            'Student Affairs' => ['sao', 'student affairs', 'student id', 'id card', 'organization', 'club', 'counseling', 'guidance', 'discipline'],
            'Campus Facilities' => ['restroom', 'toilet', 'cr', 'canteen', 'cafeteria', 'hallway', 'corridor', 'drinking fountain', 'elevator', 'ventilation', 'ac'],
            'Maintenance' => ['maintenance', 'broken chair', 'broken desk', 'light bulb', 'leak', 'plumbing', 'electrical', 'wire', 'water'],
            'Safety and Security' => ['security', 'guard', 'safety', 'theft', 'stolen', 'hazard', 'fire hazard', 'emergency', 'harassment', 'threat', 'injury'],
            'Other Operational Concerns' => ['clinic', 'health services', 'parking', 'lost and found'],
        ],
        'Other / Needs Review' => [
            'General Inquiries' => ['inquiry', 'question', 'ask', 'how to', 'general', 'info', 'information'],
            'Uncategorized / Needs Review' => ['other', 'misc', 'miscellaneous', 'feedback', 'suggestion'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Priority Determination Guidelines
    |--------------------------------------------------------------------------
    |
    | Rules for objective, content-based priority assessment without relying solely
    | on urgency adjectives or angry sentiment.
    |
    */
    'priority_rules' => [
        'critical' => [
            'indicators' => [
                'campus_wide' => ['entire campus', 'campus-wide', 'whole university', 'all students', 'all buildings'],
                'safety_hazard' => ['fire', 'exposed wire', 'electrical spark', 'gas leak', 'structural damage', 'hazard', 'safety risk', 'physical threat', 'emergency'],
                'security_breach' => ['ransomware', 'malware spreading', 'data breach', 'hacked', 'data leak', 'credential leak'],
                'total_blackout' => ['power outage', 'blackout', 'total network failure', 'all servers down'],
            ],
            'min_confidence' => 0.85,
        ],
        'high' => [
            'indicators' => [
                'department_wide' => ['entire lab', 'whole lab', 'all computers in lab', 'department-wide', 'multiple classrooms'],
                'essential_service' => ['enrollment system down', 'portal offline', 'cashier offline', 'exam disruption', 'midterm', 'finals', 'grading deadline'],
                'exam_blocker' => ['cannot take exam', 'ongoing exam', 'laboratory exam blocked'],
            ],
            'min_confidence' => 0.75,
        ],
        'medium' => [
            'indicators' => [
                'single_user_academic' => ['my computer', 'pc 12', 'single machine', 'projector flickering', 'intermittent connection'],
                'workaround_available' => ['can use other pc', 'partial function', 'inconvenience'],
            ],
            'min_confidence' => 0.60,
        ],
        'low' => [
            'indicators' => [
                'general_request' => ['question', 'how to', 'inquiry', 'minor suggestion', 'request for information', 'cosmetic'],
            ],
            'min_confidence' => 0.50,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Summary Options
    |--------------------------------------------------------------------------
    */
    'max_summary_length' => 300,
];
