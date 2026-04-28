export type Lane = {
  slug: string;
  eyebrow: string;
  title: string;
  summary: string;
  bullets: string[];
};

export const lanes: Lane[] = [
  {
    slug: "local-first-crm",
    eyebrow: "Office system",
    title: "Local-first CRM for real credit repair operations",
    summary: "Leads, clients, reports, letters, billing signals, and follow-up live in the office node instead of being scattered across disconnected SaaS tools.",
    bullets: ["Client roster and provider credentials stay local", "PWA-style operator workspace", "Audit trails for notes, reports, tasks, and letters"],
  },
  {
    slug: "metro2",
    eyebrow: "Review engine",
    title: "Metro2 review that starts with the report data",
    summary: "CreditSoft is shaped around Metro2 issues first, then letters and workflows follow the data instead of generic template guessing.",
    bullets: ["AI-assisted issue detection", "Account mismatch and reporting-status review", "Dispute prep tied to evidence and client records"],
  },
  {
    slug: "client-portal",
    eyebrow: "Client lane",
    title: "Client portal connected to the intranet",
    summary: "The public website and portal can collect uploads, show status, and hand client actions back to the protected office system.",
    bullets: ["Requested document uploads", "Client-safe status updates", "Portal events written back to the CRM"],
  },
  {
    slug: "api-bridge",
    eyebrow: "Connectivity",
    title: "Website and portal API bridge",
    summary: "Public intake, portal tools, Meta lead flows, and browser companion captures can route into the local office node through controlled API lanes.",
    bullets: ["Bearer-token API access", "ngrok or stable public callback lane", "Local and tailnet paths for office devices"],
  },
  {
    slug: "multi-office-nodes",
    eyebrow: "Node topology",
    title: "Multi-office nodes for different cities, states, or VA offices",
    summary: "The intended deployment is not one fragile machine. Offices can run primary, replica, and backup-light nodes in different places, with each node's backup role matched to the hardware it actually has.",
    bullets: ["Replica roles should match the weakest node", "Different physical locations supported", "Wasabi, Dropbox, Google Drive, and local drives"],
  },
  {
    slug: "backup-redundancy",
    eyebrow: "Continuity",
    title: "Backup redundancy between office nodes",
    summary: "Office nodes should be able to protect each other so one failed desktop, workstation, server, or connection does not become the whole business outage.",
    bullets: ["PostgreSQL-backed office data", "Local storage and backup lanes", "Public routing separated from private data"],
  },
  {
    slug: "tech-stack",
    eyebrow: "Deployment",
    title: "Handled install stack for serious offices",
    summary: "CreditSoft uses a practical stack: PostgreSQL, local containers, PWA access, and controlled public callback lanes.",
    bullets: ["PostgreSQL office database", "Containerized intranet services", "PWA operator access and API health checks"],
  },
  {
    slug: "email-providers",
    eyebrow: "Delivery",
    title: "Email providers and CRM notifications",
    summary: "Office notices, billing follow-up, provider-login alerts, and portal messages need reliable delivery through the provider an office already trusts.",
    bullets: ["Microsoft 365 and Google Workspace", "Amazon SES, SendGrid, Mailgun, Postmark, Brevo, SMTP.com", "Custom SMTP support"],
  },
  {
    slug: "compliance",
    eyebrow: "Federal workflows",
    title: "FCRA / FDCPA workflow support",
    summary: "The system should help spot potential FCRA and FDCPA issues, preserve evidence, and support proper next steps without pretending to replace legal advice.",
    bullets: ["FCRA claim indicator awareness", "FDCPA collection-practice context", "Evidence-first dispute and escalation workflow"],
  },
  {
    slug: "disputes",
    eyebrow: "Casework",
    title: "Disputes backed by Metro2 and evidence",
    summary: "CreditSoft keeps the dispute process connected to the report data, uploaded documents, bureau comparisons, and client history.",
    bullets: ["Letters tied to violations", "Supporting documents kept with the client file", "Follow-up tasks and audit trails"],
  },
  {
    slug: "reporting",
    eyebrow: "Progress",
    title: "Reporting and monthly comparisons",
    summary: "Monthly report imports should show what changed, what got worse, and what needs a fresh dispute or escalation.",
    bullets: ["Score and status history", "Monthly bureau comparisons", "Client-safe reporting outputs"],
  },
  {
    slug: "social-media",
    eyebrow: "Marketing lane",
    title: "Social and Meta manager",
    summary: "CreditSoft's public and office stack should connect content planning, Meta lead capture, and office intake instead of treating marketing as a separate island.",
    bullets: ["Content calendar direction", "Lead handoff into intake", "Creator goals and follow-up visibility"],
  },
  {
    slug: "websites",
    eyebrow: "Public sites",
    title: "Managed websites connected to the CRM",
    summary: "Branded client sites should look polished while still connecting intake, portal, and updates back into the protected intranet.",
    bullets: ["Branded public front ends", "Portal and intake integration", "No default PII exposure on public pages"],
  },
  {
    slug: "migration",
    eyebrow: "Switching systems",
    title: "Migration without losing the office shape",
    summary: "Imports, browser companion captures, provider credentials, and staged documents should move offices away from old tools without making clients upload everything again.",
    bullets: ["Client and file imports", "Provider account capture", "Migration review before cutover"],
  },
  {
    slug: "outsourcing",
    eyebrow: "Automation strategy",
    title: "Less VA processing, more AI-assisted review",
    summary: "The long-term point is to reduce manual VA report processing by letting Metro2 and compliance signals trigger the work that needs human review.",
    bullets: ["Report review automation", "Issue-triggered workflows", "Human review where judgment still matters"],
  },
  {
    slug: "videos",
    eyebrow: "Walkthroughs",
    title: "CreditSoft videos and product walkthroughs",
    summary: "Demo videos should explain the public site, CRM, portal, Metro2 review, migration, and multi-node setup clearly.",
    bullets: ["Product walkthroughs", "Migration demos", "Office-node setup explainers"],
  },
  {
    slug: "roadmap",
    eyebrow: "Direction",
    title: "CreditSoft roadmap",
    summary: "The roadmap is focused on making the public site, local office nodes, CRM, portal, and AI review lane feel like one coherent system.",
    bullets: ["Astro public site", "admin.creditsoft.app CRM lane", "Cross-platform office-node topology"],
  },
];
