import { T } from "./colors";

export const MOCK_USER = {
  id: 1,
  firstname: "Maria",
  lastname: "Llavore",
  program: "BS Computer Science",
  year_graduated: 2022,
  position: "Junior Developer",
  company: "Accenture Philippines",
  address: "Quezon City, Metro Manila",
  email: "maria.llavore@email.com",
  personal_contact: "+63 912 345 6789",
  employment_status: "Employed",
  profile_completion: 73,
  skills: ["React", "Node.js", "Python", "SQL", "Figma", "TypeScript"],
};

export const MOCK_ANNOUNCEMENTS = [
  {
    id: 1,
    title: "OLFU CCS Homecoming 2025",
    content:
      "Join us for our annual alumni homecoming at OLFU Fairview Campus. Reconnect with fellow graduates and celebrate milestones!",
    created_at: "2025-05-05",
    has_image: true,
  },
  {
    id: 2,
    title: "New Career Portal Features",
    content:
      "We've updated the career portal with new job listings from top tech companies in Metro Manila.",
    created_at: "2025-04-28",
    has_image: false,
  },
];

export const MOCK_EVENTS = [
  {
    id: 1,
    title: "Tech Talk: AI in the Modern Workplace",
    event_date: "2025-06-15",
    event_time: "14:00:00",
    location: "OLFU Fairview, Hall A",
    type: "Seminar",
    registered_count: 48,
    spots_left: 2,
    is_registered: 0,
  },
  {
    id: 2,
    title: "Alumni Job Fair 2025",
    event_date: "2025-06-22",
    event_time: "08:00:00",
    location: "Quezon City Sports Club",
    type: "Career",
    registered_count: 128,
    spots_left: 72,
    is_registered: 1,
  },
  {
    id: 3,
    title: "CCS Alumni General Assembly",
    event_date: "2025-07-10",
    event_time: "09:00:00",
    location: "OLFU Antipolo Campus",
    type: "General",
    registered_count: 35,
    spots_left: 65,
    is_registered: 0,
  },
  {
    id: 4,
    title: "Web Dev Workshop: Next.js 15",
    event_date: "2025-04-10",
    event_time: "10:00:00",
    location: "OLFU CCS Lab",
    type: "Workshop",
    registered_count: 30,
    spots_left: 0,
    is_registered: 1,
  },
];

export const MOCK_JOBS = [
  {
    id: 1,
    title: "Junior Software Developer",
    company: "Accenture Philippines",
    location: "BGC, Taguig",
    tags: ["React", "Node.js"],
    type: "Full-time",
  },
  {
    id: 2,
    title: "Data Analyst",
    company: "Globe Telecom",
    location: "Mandaluyong",
    tags: ["Python", "SQL"],
    type: "Full-time",
  },
];

export const MOCK_DIRECTORY = [
  {
    id: 2,
    firstname: "Juan",
    lastname: "Dela Cruz",
    program: "BS Computer Science",
    year_graduated: 2021,
    position: "Software Engineer",
    company: "Grab",
    initials: "JD",
    color: [T.leaf, T.moss],
  },
  {
    id: 3,
    firstname: "Ana",
    lastname: "Lim",
    program: "BS Information Technology",
    year_graduated: 2020,
    position: "Data Analyst",
    company: "Globe Telecom",
    initials: "AL",
    color: [T.gold, T.goldLt],
  },
  {
    id: 4,
    firstname: "Miguel",
    lastname: "Reyes",
    program: "Associate in Computer Tech.",
    year_graduated: 2022,
    position: "IT Support",
    company: "BDO Unibank",
    initials: "MR",
    color: [T.moss, T.fern],
  },
  {
    id: 5,
    firstname: "Carla",
    lastname: "Santos",
    program: "BS Computer Science",
    year_graduated: 2023,
    position: "Frontend Developer",
    company: "Accenture",
    initials: "CS",
    color: [T.forest, T.moss],
  },
];

export const MOCK_CONVERSATIONS = [
  {
    id: 1,
    name: "Juan Dela Cruz",
    initials: "JD",
    color: [T.leaf, T.moss],
    preview: "Hey! Are you going to the job fair?",
    time: "2m ago",
    unread: 1,
    online: true,
  },
  {
    id: 2,
    name: "Ana Lim",
    initials: "AL",
    color: [T.gold, T.goldLt],
    preview: "I shared a job opening you might like!",
    time: "1h ago",
    unread: 1,
    online: false,
  },
  {
    id: 3,
    name: "Miguel Reyes",
    initials: "MR",
    color: [T.moss, T.fern],
    preview: "Thanks for the referral!",
    time: "Yesterday",
    unread: 0,
    online: true,
  },
];

export const PROGRAMS = [
  "BS Computer Science",
  "BS Information Technology",
  "Associate in Computer Technology",
  "BS Information Systems",
].map((v) => ({ value: v, label: v }));

export const STEP_LABELS = ["ID Upload", "Personal", "Academic", "Career", "Account"];
