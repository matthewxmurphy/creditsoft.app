export type Lane = {
  slug: string;
  eyebrow: string;
  title: string;
  summary: string;
  bullets: string[];
  bulletDetails?: string[];
  image: string;
  alt: string;
  proofTitle: string;
  detailSections?: {
    eyebrow: string;
    title: string;
    text: string;
    items: {
      title: string;
      text: string;
    }[];
  }[];
  videoHref?: string;
  videoLabel?: string;
  galleryIntro?: string;
  gallery?: {
    src: string;
    title: string;
    alt: string;
  }[];
};

const proofBase = "/assets/images/product-proof";

export const lanes: Lane[] = [
  {
    slug: "local-first-crm",
    eyebrow: "Office system",
    title: "Local-first CRM for real credit repair operations",
    summary: "Leads, clients, reports, letters, billing signals, and follow-up live in the office node instead of being scattered across disconnected SaaS tools.",
    bullets: ["Client roster and provider credentials stay local", "PWA-style operator workspace", "Audit trails for notes, reports, tasks, and letters"],
    image: `${proofBase}/clients-roster.png`,
    alt: "CreditSoft client roster screenshot with account rows and office workflow controls.",
    proofTitle: "CRM roster",
  },
  {
    slug: "metro2",
    eyebrow: "Review engine",
    title: "Metro2 review that starts with the report data",
    summary: "CreditSoft is shaped around Metro2 issues first, then letters and workflows follow the data instead of generic template guessing.",
    bullets: ["AI-assisted issue detection", "Account mismatch and reporting-status review", "Dispute prep tied to evidence and client records"],
    image: `${proofBase}/inbox-queue.png`,
    alt: "CreditSoft inbox queue screenshot showing work items prepared for Metro2 review.",
    proofTitle: "Metro2 review queue",
  },
  {
    slug: "client-portal",
    eyebrow: "Client lane",
    title: "Client portal and installable app access",
    summary: "The public website and portal can collect uploads, show status, and hand client actions back to the protected office system while staff can launch CreditSoft as an installable desktop app.",
    bullets: ["Requested document uploads", "Client-safe status updates", "macOS Dock and app switcher PWA proof"],
    image: `${proofBase}/pwa-shortcuts-menu.png`,
    alt: "CreditSoft installed PWA on macOS showing the app icon in the app switcher and Dock.",
    proofTitle: "macOS PWA app access",
  },
  {
    slug: "api-bridge",
    eyebrow: "Connectivity",
    title: "Website and portal API bridge",
    summary: "Public intake, portal tools, Meta lead flows, and browser companion captures can route into the local office node through controlled API lanes.",
    bullets: ["Bearer-token API access", "ngrok or stable public callback lane", "Local and tailnet paths for office devices"],
    image: `${proofBase}/api-settings.png`,
    alt: "CreditSoft API settings screenshot showing integration and key controls.",
    proofTitle: "API settings",
  },
  {
    slug: "multi-office-nodes",
    eyebrow: "Node topology",
    title: "Multi-office nodes for different cities, states, or VA offices",
    summary: "The intended deployment is not one fragile machine. Offices can run primary, replica, and backup-light nodes in different places, with each node's backup role matched to the hardware it actually has.",
    bullets: ["Replica roles should match the weakest node", "Weak offices can run backup-light", "Wasabi, Dropbox, Google Drive, and local drives"],
    bulletDetails: [
      "A cluster is only as durable as the smallest node allowed to replicate, so strong nodes should carry the heavy backup work.",
      "A low-disk or low-memory office can stay useful for local work and manager visibility without being forced to store every archive.",
      "Off-node backup lanes keep replicas honest: Wasabi for durable archives, Dropbox or Google Drive for handoff, and removable drives for local copies.",
    ],
    image: `${proofBase}/multi-office-node-topology.png`,
    alt: "CreditSoft node topology visual showing a primary office node, peer office replica, backup-light weak node, and off-node backup lanes.",
    proofTitle: "Replica and backup topology",
    detailSections: [
      {
        eyebrow: "Replica policy",
        title: "Do not let the weakest machine define every office.",
        text: "Multi-office only works when each node has an honest role. Strong nodes can replicate and store archives. Weak nodes can still participate without carrying work they cannot safely hold.",
        items: [
          {
            title: "Strong nodes protect the cluster",
            text: "Desktops, workstations, or servers with enough disk and memory can act as active replicas and receive peer backup copies.",
          },
          {
            title: "Weak nodes stay backup-light",
            text: "Small offices, low-storage laptops, or small ARM devices can keep local workflow online while skipping heavy peer archive duties.",
          },
          {
            title: "Visibility stays separate from storage",
            text: "Managers can see node health and office activity without forcing every machine to behave like the main archive server.",
          },
        ],
      },
      {
        eyebrow: "Backup lanes",
        title: "Backups should not depend on only another office computer.",
        text: "CreditSoft should let each office choose local, cloud, and peer-mirror lanes based on the hardware and compliance posture of that location.",
        items: [
          {
            title: "Wasabi archive",
            text: "Use S3-compatible Wasabi storage as the durable off-machine archive when a local node should not be the only safety net.",
          },
          {
            title: "Dropbox or Google Drive handoff",
            text: "Use familiar shared-folder lanes for exports, staged document mirrors, and non-technical handoff workflows.",
          },
          {
            title: "Local flash and external drives",
            text: "Support USB flash drives, external SSDs, NVMe/SATA storage, and large local drives for offices that want physical backup copies.",
          },
        ],
      },
      {
        eyebrow: "Hardware fit",
        title: "The node can be flexible, but the math still has to be honest.",
        text: "CreditSoft should run on supported office hardware without pretending all hardware has the same storage, memory, or uptime profile.",
        items: [
          {
            title: "Supported targets",
            text: "Plan for macOS, Ubuntu/Linux/BSD, Windows 11+, and small ARM systems when they have enough memory, disk, and container support.",
          },
          {
            title: "Capacity follows the role",
            text: "A backup node needs real storage. A visibility node can be lighter. A replica node should be sized like it may need to recover the office.",
          },
          {
            title: "Locations are the point",
            text: "Nodes are meant for different cities, states, or VA offices so one building, ISP, or workstation does not become the whole business.",
          },
        ],
      },
    ],
  },
  {
    slug: "backup-redundancy",
    eyebrow: "Continuity",
    title: "Backup redundancy between office nodes",
    summary: "Office nodes should be able to protect each other so one failed desktop, workstation, server, or connection does not become the whole business outage.",
    bullets: ["PostgreSQL-backed office data", "Local storage and backup lanes", "Public routing separated from private data"],
    image: `${proofBase}/cfo-dashboard.png`,
    alt: "CreditSoft office metrics dashboard screenshot.",
    proofTitle: "Office continuity",
  },
  {
    slug: "tech-stack",
    eyebrow: "Deployment",
    title: "Handled install stack for serious offices",
    summary: "CreditSoft uses a practical stack: PostgreSQL, local containers, PWA access, and controlled public callback lanes.",
    bullets: ["PostgreSQL office database", "Containerized intranet services", "PWA operator access and API health checks"],
    image: `${proofBase}/dashboard-in-app.png`,
    alt: "CreditSoft installed app dashboard screenshot.",
    proofTitle: "Installed app stack",
  },
  {
    slug: "email-providers",
    eyebrow: "Delivery",
    title: "Email providers and CRM notifications",
    summary: "Office notices, billing follow-up, provider-login alerts, and portal messages need reliable delivery through the provider an office already trusts.",
    bullets: ["Microsoft 365 and Google Workspace", "Amazon SES, SendGrid, Mailgun, Postmark, Brevo, SMTP.com", "Custom SMTP support"],
    image: `${proofBase}/connectivity-settings.png`,
    alt: "CreditSoft connectivity settings screenshot used for provider and delivery configuration.",
    proofTitle: "Provider connectivity",
  },
  {
    slug: "compliance",
    eyebrow: "Federal workflows",
    title: "FCRA / FDCPA workflow support",
    summary: "The system should help spot potential FCRA and FDCPA issues, preserve evidence, and support proper next steps without pretending to replace legal advice.",
    bullets: ["FCRA claim indicator awareness", "FDCPA collection-practice context", "Evidence-first dispute and escalation workflow"],
    image: `${proofBase}/tasks-board.png`,
    alt: "CreditSoft tasks board screenshot showing assigned work and workflow status.",
    proofTitle: "Compliance tasks",
  },
  {
    slug: "disputes",
    eyebrow: "Casework",
    title: "Disputes backed by Metro2 and evidence",
    summary: "CreditSoft keeps the dispute process connected to the report data, uploaded documents, bureau comparisons, and client history.",
    bullets: ["Letters tied to violations", "Supporting documents kept with the client file", "Follow-up tasks and audit trails"],
    image: `${proofBase}/tasks-board.png`,
    alt: "CreditSoft tasks board screenshot showing assigned work and workflow status.",
    proofTitle: "Dispute workflow",
  },
  {
    slug: "reporting",
    eyebrow: "Progress",
    title: "Reporting and monthly comparisons",
    summary: "Monthly report imports should show what changed, what got worse, and what needs a fresh dispute or escalation.",
    bullets: ["Score and status history", "Monthly bureau comparisons", "Client-safe reporting outputs"],
    image: `${proofBase}/cfo-dashboard.png`,
    alt: "CreditSoft office metrics dashboard screenshot.",
    proofTitle: "Reporting dashboard",
  },
  {
    slug: "social-media",
    eyebrow: "Marketing lane",
    title: "Social and Meta manager",
    summary: "CreditSoft connects content planning, Meta lead capture, creator challenge scoring, replies, ads, and office intake so marketing is part of the operating system instead of a separate island.",
    bullets: ["Meta content calendar and publishing queue", "Lead ads and reply handoff into intake", "Creator goals, weekly planning, and follow-up visibility"],
    image: `${proofBase}/social-meta-workspace.jpg`,
    alt: "CreditSoft Social and Meta workspace showing the content calendar, publish queue, creator challenge, replies, and lead ads.",
    proofTitle: "Social / Meta workspace",
    videoHref: "/videos",
    videoLabel: "Watch the videos",
    gallery: [
      {
        src: `${proofBase}/social-calendar-overview.jpg`,
        title: "Social calendar overview",
        alt: "CreditSoft social calendar overview showing connected social planning inside the office app.",
      },
      {
        src: `${proofBase}/social-calendar-month.jpg`,
        title: "Monthly content calendar",
        alt: "CreditSoft monthly social content calendar with scheduled posts and publishing lanes.",
      },
      {
        src: `${proofBase}/social-weekly-plan.jpg`,
        title: "Weekly planning lane",
        alt: "CreditSoft weekly social planning view with selected days and scheduling controls.",
      },
      {
        src: `${proofBase}/social-calendar-command.jpg`,
        title: "Calendar command view",
        alt: "CreditSoft Social and Meta manager command view for planning and follow-up.",
      },
    ],
  },
  {
    slug: "websites",
    eyebrow: "Public sites",
    title: "Managed websites connected to the CRM",
    summary: "Branded client sites should look polished while still connecting intake, portal, and updates back into the protected intranet.",
    bullets: ["Branded public front ends", "Portal and intake integration", "No default PII exposure on public pages"],
    image: `${proofBase}/dashboard-in-browser.png`,
    alt: "CreditSoft dashboard screenshot running in a browser window.",
    proofTitle: "Website bridge",
  },
  {
    slug: "migration",
    eyebrow: "Switching systems",
    title: "Migration without losing the office shape",
    summary: "Imports, browser companion captures, provider credentials, and staged documents should move offices away from old tools without making clients upload everything again.",
    bullets: ["Client and file imports", "Provider account capture", "Migration review before cutover"],
    image: `${proofBase}/clients-roster.png`,
    alt: "CreditSoft client roster screenshot with account rows and office workflow controls.",
    proofTitle: "Migration roster",
  },
  {
    slug: "outsourcing",
    eyebrow: "Automation strategy",
    title: "Less VA processing, more AI-assisted review",
    summary: "The long-term point is to reduce manual VA report processing by letting Metro2 and compliance signals trigger the work that needs human review.",
    bullets: ["Report review automation", "Issue-triggered workflows", "Human review where judgment still matters"],
    image: `${proofBase}/inbox-queue.png`,
    alt: "CreditSoft inbox queue screenshot showing work items prepared for review.",
    proofTitle: "Automation queue",
  },
  {
    slug: "roadmap",
    eyebrow: "Direction",
    title: "CreditSoft roadmap",
    summary: "The roadmap is focused on making the public site, local office nodes, CRM, portal, and AI review lane feel like one coherent system.",
    bullets: ["Astro public site", "admin.creditsoft.app CRM lane", "Cross-platform office-node topology"],
    image: `${proofBase}/dashboard-in-app.png`,
    alt: "CreditSoft installed app dashboard screenshot.",
    proofTitle: "Roadmap workspace",
  },
];
