<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Skill;

class SkillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $skills = [
            // Programming Languages
            'PHP',
            'JavaScript',
            'Python',
            'Java',
            'C#',
            'C++',
            'TypeScript',
            'Go',
            'Ruby',
            'Swift',
            'Kotlin',
            'Dart',
            
            // Web Frameworks & Libraries
            'Laravel',
            'React',
            'Vue.js',
            'Angular',
            'Node.js',
            'Express.js',
            'Next.js',
            'Nuxt.js',
            'Django',
            'Flask',
            'Spring Boot',
            'ASP.NET',
            'CodeIgniter',
            'Symfony',
            
            // Mobile Development
            'React Native',
            'Flutter',
            'iOS Development',
            'Android Development',
            'Ionic',
            'Xamarin',
            
            // Frontend Technologies
            'HTML',
            'CSS',
            'SCSS/SASS',
            'Bootstrap',
            'Tailwind CSS',
            'Material-UI',
            'Styled Components',
            'jQuery',
            'Alpine.js',
            
            // Backend & Databases
            'MySQL',
            'PostgreSQL',
            'MongoDB',
            'Redis',
            'SQLite',
            'Oracle',
            'Microsoft SQL Server',
            'Firebase',
            'Supabase',
            
            // Cloud & DevOps
            'AWS',
            'Google Cloud Platform',
            'Microsoft Azure',
            'Docker',
            'Kubernetes',
            'CI/CD',
            'Git',
            'GitHub',
            'GitLab',
            'Jenkins',
            'Nginx',
            'Apache',
            
            // API & Integration
            'REST API',
            'GraphQL',
            'Webhooks',
            'OAuth',
            'JWT',
            'API Documentation',
            'Postman',
            
            // CMS & E-commerce
            'WordPress',
            'Drupal',
            'Joomla',
            'Shopify',
            'WooCommerce',
            'Magento',
            'OpenCart',
            
            // Design & UI/UX
            'Figma',
            'Adobe Photoshop',
            'Adobe Illustrator',
            'Adobe XD',
            'Sketch',
            'Canva',
            'UI/UX Design',
            'Responsive Design',
            'Prototyping',
            'Wireframing',
            
            // Digital Marketing
            'SEO',
            'Content Writing',
            'Copywriting',
            'Social Media Marketing',
            'Google Ads',
            'Facebook Ads',
            'Email Marketing',
            'Content Marketing',
            'Affiliate Marketing',
            'Influencer Marketing',
            
            // Data & Analytics
            'Data Analysis',
            'Excel',
            'Google Sheets',
            'Power BI',
            'Tableau',
            'Google Analytics',
            'SQL',
            'Data Visualization',
            'Machine Learning',
            'AI Integration',
            
            // Project Management
            'Project Management',
            'Agile',
            'Scrum',
            'Kanban',
            'Jira',
            'Trello',
            'Asana',
            'Monday.com',
            
            // Business & Finance
            'Financial Analysis',
            'Accounting',
            'QuickBooks',
            'Business Analysis',
            'Market Research',
            'Business Strategy',
            'Sales Funnel',
            'Lead Generation',
            
            // Quality Assurance
            'Manual Testing',
            'Automated Testing',
            'Test Cases',
            'Bug Reporting',
            'Quality Assurance',
            'User Acceptance Testing',
            
            // Video & Audio
            'Video Editing',
            'Adobe Premiere',
            'Final Cut Pro',
            'After Effects',
            'Audio Editing',
            'Animation',
            '3D Modeling',
            
            // Specialized Tools
            'Stripe Integration',
            'PayPal Integration',
            'Twilio',
            'SendGrid',
            'Mailchimp',
            'HubSpot',
            'Salesforce',
            'Zapier',
            'GDPR Compliance',
            'HIPAA Compliance',
        ];

        foreach ($skills as $skillName) {
            Skill::create([
                'name' => $skillName,
                'status' => true,
            ]);
        }
    }
}
