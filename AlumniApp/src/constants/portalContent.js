/** Static portal content aligned with the web (al_about, gen_faqs, alumni_card_details). */

export const ABOUT_CONTENT = {
  title: "About the Alumni Portal",
  intro:
    "The OLFU Alumni Management System connects graduates across campuses with events, career opportunities, and the alumni directory.",
  mission:
    "Our mission is to strengthen the OLFU alumni community through verified profiles, digital alumni ID, job board, and ongoing engagement with the university.",
  features: [
    { icon: "people-outline", title: "Alumni Directory", text: "Find and connect with fellow graduates." },
    { icon: "briefcase-outline", title: "Career Center", text: "Browse jobs and manage your career timeline." },
    { icon: "calendar-outline", title: "Events", text: "Stay updated on reunions and campus activities." },
    { icon: "card-outline", title: "Digital Alumni Card", text: "Verified ID card for alumni benefits." },
  ],
};

export const ALUMNI_CARD_CONTENT = {
  title: "Alumni Card",
  subtitle: "Official OLFU CCS digital alumni identification",
  benefits: [
    "Valid for three (3) years from issuance",
    "First card free for verified graduates",
    "Access to partner discounts and campus services",
    "Renewal available through the registration office",
  ],
  steps: [
    "Complete alumni registration online",
    "Wait for admin verification of your documents",
    "Download or print your card from your profile when approved",
  ],
  note: "For card renewal or replacement fees, contact the CCS Alumni Affairs office.",
};

export const FAQ_SECTIONS = {
  General: [
    {
      q: "What is the OLFU Alumni Management System?",
      a: "It is the official digital platform connecting OLFU graduates with each other and the institution.",
    },
    {
      q: "Who can register?",
      a: "All graduates of OLFU across campuses and programs.",
    },
    {
      q: "How do I create an account?",
      a: "Use Register on the login screen and complete the alumni registration form with your documents.",
    },
    {
      q: "I forgot my password. What do I do?",
      a: "Use Forgot Password on the web portal login page to reset via email.",
    },
  ],
  "Profile & Account": [
    {
      q: "How do I update my personal information?",
      a: "Open the menu → Edit Profile, or My Profile → edit icon.",
    },
    {
      q: "How do I change my profile photo?",
      a: "Go to Edit Profile and tap the photo to upload a new image.",
    },
  ],
  "Alumni Card": [
    {
      q: "How do I apply for an Alumni Card?",
      a: "Submit the online registration form and wait for verification.",
    },
    {
      q: "How long is the card valid?",
      a: "Three (3) years from the date of issuance.",
    },
  ],
};
