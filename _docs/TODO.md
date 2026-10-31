# Farmer Panel Fix TODO

## Plan Steps (Approved)
- [x] Step 1: Fix PHP stats queries (farm status multi, notifications)
- [x] Step 2: Update dashboard cards (4 stats grid)
- [x] Step 3: Add Farm Registration modal/form
- [x] Step 4: Add Livestock monitoring table/form
- [x] Step 5: Add Incident reporting form + list
- [x] Step 6: Add Notifications table
- [x] Step 7: Add Profile edit modal
- [x] Step 8: JS enhancements (dynamic nav, toasts)
- [ ] Step 9: Test & finalize

---

## Technical Debt & Refactoring Plan (To Do Tomorrow)

### Phase 1: Global Initialization (Completed Today)
- [x] Created `init.php` for centralizing `session_start()` and `db_connect.php`.
- [x] Refactored `farmer.php`, `admin.php`, `agriDA.php`, `index.php`, `about.php`, `contact.php`, `dashboard.php`, and `ecertificate.php` to use `init.php`.
- [x] Linted entire project and fixed dangling syntax errors.
- [x] Created `organize-php-file` AI skill.

### Phase 2: Decouple Logic from Display in `farmer.php`
- [x] Extract "Profile Update" logic (`$_POST['update_profile']`) into `api/update_profile.php`
- [x] Extract "Farm Registration" logic (`$_POST['action']==='register_farm'`) into `api/register_farm.php`
- [x] Extract "Livestock Add/Update" logic into `api/livestock.php`
- [x] Extract "Incident Report" logic into `api/report_incident.php`
- [x] Verify frontend HTML forms/AJAX requests are properly aiming at the new `api/` endpoints.

### Phase 3: UI Component Extraction (HTML Cleanup)
- [x] Create `includes/` folder for reusable HTML partials.
- [x] Extract Sidebar navigation into `includes/farmer_sidebar.php`.
- [x] Extract common Dashboard Top-bar into `includes/topbar.php`.
- [x] Centralize Modals (like `edit-profile-modal.php`) so `farmer.php` is much shorter and easier to read.

### Phase 4: Security & Architecture Polish
- [x] **Implement Global CSRF Protection**: Generate token in `init.php`, pass to frontend, and validate on all `POST` requests in `api/`.
- [x] **Centralize API guards**: Create `api/guard.php` to handle session/role checks and prevent copy-paste leaks.
- [x] **Secure File Uploads**: Validate images via magic bytes (`finfo`) instead of path extensions or forced base64 saving.
- [ ] **DRY out Layouts**: Create `includes/layout_header.php` and `includes/layout_footer.php` to prevent duplicating `<head>`, CDN links, and wrappers across pages.
- [ ] **CSS Clean-up & Standardization**: Move UI specific inline-styles (like `style="color:var(--danger)"` or flexbox layouts) into `assets/style.css` using utility classes. Consistent typography and BEM-style or utility classes to reduce CSS bloat.

### Phase 5: n8n Workflow Automation & AI (Next Few Days)
- [ ] **AI Chatbot**: Build a smart farming assistant chatbot. PHP frontend queries an n8n webhook which handles the LangChain/OpenAI logic, generates responses (e.g., crop disease advice), and optionally interacts with the SQL database directly.
- [ ] **Incident Response (Early Warning System)**: Trigger webhook on `api/report_incident.php`. Use n8n to send SMS/Viber warnings to farmers within 10km and alert DA officers.
- [ ] **Public Reporting Triage**: Connect `public-report-form.php` to an n8n webhook to automatically create and assign triage tickets in a task tracker (e.g., Trello/Jira).
- [ ] **Automated Onboarding & Approvals**: Send webhook when an admin approves a farm. Use n8n to automatically generate E-Certificate PDFs and email/SMS them to the farmer.
- [ ] **Scheduled Reporting (Cron)**: Set up n8n scheduled workflows to query the database weekly and automatically email "Weekly Agricultural Status Reports" to stakeholders.
- [ ] **Omnichannel Chat Integration**: Bridge the internal farmer chat with external apps (Viber/Messenger) via n8n so farmers without active web sessions don't miss messages.

### Phase 6: Advanced Analytics (Dashboard Visuals)
- [ ] **1st District Interactive Chart**: Add a dynamic Chart.js graphic in the dashboard for livestock totals.
- [ ] **Municipality Filter UI**: Implement a dropdown filter to let DA officers sort livestock and farm data by specific municipalities (e.g., Lupi, Ragay).
- [ ] **API Endpoint for Stats**: Create `api/district_stats.php` to fetch SQL queries grouping livestock by type and municipality dynamically.


