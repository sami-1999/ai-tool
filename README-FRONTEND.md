# AI Tool Frontend - How to Run

## Prerequisites
- Node.js (version 18 or higher)
- Laravel API backend running on port 8000

## Quick Start

### 1. Install Dependencies
```bash
cd frontend
npm install
```

### 2. Start Laravel Backend (in separate terminal)
```bash
# In the main ai-tool directory
php artisan serve
```
This will start Laravel on http://localhost:8000

### 3. Start Next.js Frontend
```bash
# In the frontend directory
cd frontend
npm run dev
```
This will start the Next.js app on http://localhost:3000

## Project Structure

```
frontend/
├── src/
│   ├── app/                 # Next.js app directory (pages)
│   │   ├── page.tsx         # Home page
│   │   ├── login/page.tsx   # Login page
│   │   ├── register/page.tsx # Registration page
│   │   └── dashboard/page.tsx # Dashboard
│   ├── lib/                 # Utilities
│   │   └── api.ts           # API client configuration
│   ├── services/            # API service layers
│   │   ├── auth.ts          # Authentication services
│   │   ├── skills.ts        # Skills management
│   │   ├── projects.ts      # Project management
│   │   ├── proposals.ts     # AI proposal generation
│   │   └── userProfile.ts   # User profile management
│   └── types/               # TypeScript type definitions
│       └── index.ts         # All interface definitions
├── .env.local              # Environment variables
└── package.json            # Dependencies and scripts
```

## Available Pages

1. **Home Page** (http://localhost:3000)
   - Landing page with features overview
   - Call-to-action buttons for login/register

2. **Login** (http://localhost:3000/login)
   - User authentication
   - Redirects to dashboard on success

3. **Register** (http://localhost:3000/register)
   - New user registration
   - Account creation with validation

4. **Dashboard** (http://localhost:3000/dashboard)
   - Overview of user statistics
   - Quick access to skills, projects, and proposals

## Features

### Authentication
- Login/Register functionality
- JWT token management
- Auto-redirect for unauthorized access
- Password reset capability

### Skills Management
- Add/Edit/Delete skills
- Proficiency levels (beginner, intermediate, advanced, expert)
- Years of experience tracking

### Project Management
- Create and manage projects
- Budget and deadline tracking
- Project status management
- Skill requirements mapping

### AI Proposals
- Generate proposals using AI
- Project-based proposal creation
- Proposal feedback system

### User Profile
- Profile information management
- Professional details
- Social links and contact info

## Environment Configuration

The `.env.local` file contains:
```
NEXT_PUBLIC_API_BASE_URL=http://localhost:8000/api
```

Make sure your Laravel API is running on the correct port.

## Troubleshooting

### If Next.js won't start:
1. Make sure you're in the `frontend` directory
2. Run `npm install` to install dependencies
3. Check if port 3000 is available

### If API calls fail:
1. Ensure Laravel backend is running on http://localhost:8000
2. Check CORS settings in Laravel
3. Verify API endpoints match the service calls

### For TypeScript errors:
1. The project uses TypeScript for better development experience
2. All API responses are typed for safety
3. Run `npm run build` to check for type errors

## Development Commands

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run start` - Start production server
- `npm run lint` - Run ESLint

## Technology Stack

- **Next.js 16** - React framework with App Router
- **TypeScript** - Type safety and better DX
- **Tailwind CSS** - Utility-first styling
- **Axios** - HTTP client for API calls
- **React 19** - Latest React features
