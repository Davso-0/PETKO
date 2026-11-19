# PETKO 10-Minute Academic Demonstration

**Audience:** University faculty panel
**Total target:** 8–10 minutes
**Language:** English
**Project:** PETKO — Veterinary Clinic, Pharmacy, and Multi-Branch Management

---

## 1. ZUZU — Home Page Experience (~1.5 min)

### Step 1.1: Navigation and Clinical–Agropecuario Proposal

**[Screen Movement / Action]:**
Open the Home page; briefly show the navigation bar, then scroll through the Pet and Agricultural category tabs.

**[Spoken Script]:**
“PETKO is a role-oriented management platform for veterinary clinics that may also serve pharmacy and agropecuario operations. The navigation presents the complete functional scope: Home, Features, Pets, Sales, Inventory, Branches, and About Us. The main Hero communicates that the platform supports the clinic and also individual companion-animal care. Its category tabs include dogs, cats, rabbits, birds, rodents, and agricultural animals, reflecting different clinical and livestock workflows within one interface.”

### Step 1.2: Operational Preview and Multi-Branch Model

**[Screen Movement / Action]:**
Scroll through the Sales, Inventory, Appointments, and Branch sections.

**[Spoken Script]:**
“The following sections connect the public presentation with the operating system. Sales and appointments organize front-office activity, inventory displays controlled quantities and states, and the branch model separates the responsibilities of branches, managers, and cashiers. This is a central architecture rather than a collection of unrelated screens: products and stock belong to the relational database, while the interface presents role-specific operational views.”

### Step 1.3: Session-Based Routing

**[Screen Movement / Action]:**
Point to “Get Started” and click or show the resulting route based on the active session.

**[Spoken Script]:**
“The final call to action demonstrates session-aware routing. An Administrator, Manager, or Cashier reaches the dashboard associated with its role, while an unauthenticated visitor is directed to login. Role and branch data are stored in the session after authentication, allowing the application to control navigation and reduce unnecessary transitions. The route begins on the public experience but immediately adapts to the authenticated user.”

---

## 2. JUAREZ LARA — Product Catalog Experience (~2 min)

### Step 2.1: Dynamic Loading and Instant Filters

**[Screen Movement / Action]:**
Open the Catalog, show the product and category counters, type in Search, and select a category.

**[Spoken Script]:**
“The catalog loads active products, categories, prices, images, and inventory quantities from MySQL. The product and category counters are generated from database records instead of static text. Search and category controls allow immediate filtering, with common categories including Medicines, Food, Antiparasitics, Hygiene, and Accessories. The public experience therefore reflects the same product information maintained through management modules.”

### Step 2.2: Stock-Aware Product Cards

**[Screen Movement / Action]:**
Hover over or open a product card and point to the price, stock badge, and unavailable state.

**[Spoken Script]:**
“Each card combines information from products, categories, and inventory. It displays name, category, price, image, and the quantity available. Stock is classified as available, low quantity, or out of stock, and the add-to-cart action is disabled for an unavailable item. A product detail modal presents a larger image and description. Images resolve from the stored database value or local fallback resources, ensuring the catalog remains readable when no specific image is available.”

### Step 2.3: Cart, Reservation, and Digital Ticket

**[Screen Movement / Action]:**
Add products to the cart, adjust the quantity, open checkout, and demonstrate one ticket path.

**[Spoken Script]:**
“The cart enforces the stock quantity loaded for each product, preventing a shopper from adding more units than the catalog reports. Checkout offers immediate payment or reservation and pickup at a branch. The reservation flow indicates a 48-hour holding policy, while the ticket flow generates a unique folio, date, payment or pickup method, item list, status, total, and visual barcode. This interface is the public reservation and purchase experience; completed counter transactions are recorded separately through the POS module.”

---

## 3. MIA — Login and Registration Experience (~2 min)

### Step 3.1: Secure Login and Registration

**[Screen Movement / Action]:**
Open Login and Registration; point briefly to password, confirmation, CSRF, and the terms dialog.

**[Spoken Script]:**
“Traditional authentication begins with CSRF-token validation and a database lookup for the active account. Passwords are stored as cryptographic hashes and verified only by the authentication process. Registration validates every required field, email format, matching confirmation, and the terms acceptance. Public passwords require at least eight characters, an uppercase letter, and a number. Secure cookie settings, session regeneration, HTTP-only attributes, and protected logout behavior reduce fixation and session-hijacking risks.”

### Step 3.2: Employee Activation and Automated Email

**[Screen Movement / Action]:**
Switch to employee account creation or show an activation email if available.

**[Spoken Script]:**
“Administrators can create Manager or Cashier accounts with a role and assigned branch. PETKO creates the account in a pending state and generates a personal activation token with a 24-hour expiration. PHPMailer, installed through Composer, sends an HTML email containing role, branch, terms, and an activation link. The employee establishes an individual password instead of receiving a shared credential. SMTP configuration is environment-based, so deployment credentials are not stored directly in application files.”

### Step 3.3: Google Sign-In and Role Routing

**[Screen Movement / Action]:**
Return to Login and activate Google Sign-In.

**[Spoken Script]:**
“Google Sign-In uses the Composer Google API library. PETKO validates the token, verified email, client identifier, issuer, identity subject, and expiration before linking or creating the account. It also prevents a Google identity and email address from being attached to conflicting database accounts. After authentication, the session identifier is regenerated and the user is routed according to role: Administrator to administration, Manager or Cashier to branch operations, and Customer to the public catalog or account area.”

---

## 4. DAVID — Dashboards and Administrative Management (~2 min)

### Step 4.1: Administrator Metrics and Inventory Management

**[Screen Movement / Action]:**
Sign in as Administrator; show the overview, then open Products or Inventory.

**[Spoken Script]:**
“The Administrator overview summarizes branches, users, products, inventory pieces and value, and daily sales metrics calculated from MySQL. Product management supports categories, prices, descriptions, images, active status, and assignment across branches. Updates are transactional: the product record and branch inventory records are written coherently, and the public catalog reads those records on its next query. PETKO prevents deleting a product that still has stock in any branch, protecting active inventory and historical integrity.”

### Step 4.2: Branches, Roles, and Personnel

**[Screen Movement / Action]:**
Open Branches and Personnel; show role and branch assignment controls.

**[Spoken Script]:**
“Branch management controls name, address, telephone, contact, and status. A branch with recorded sales is marked inactive instead of deleted, preserving the sales report and referential context. If it has no operational history, related inventory and cashier registrations can be removed in a transaction. Personnel management assigns Administrator, Manager, or Cashier roles and a branch to operational accounts. Duplicate emails are rejected, and employees receive secure activation links through the email service.”

### Step 4.3: Normalized Reports and Isolated Manager Dashboard

**[Screen Movement / Action]:**
Open Sales and an individual sale detail, then switch to or show the Manager dashboard.

**[Spoken Script]:**
“Sales reporting reads a normalized sale header and sale-detail lines. The report exposes date, branch, employee, payment method, total, status, quantities, and subtotals. The Manager dashboard is different: its operations are strictly scoped to the assigned branch. It shows branch revenue, sales, low stock, active registers, and recent performance while restricting inventory and sales actions to that branch. This separation demonstrates both centralized reporting and distributed operational access.”

---

## 5. RICARDO — Point of Sale, Cash Control, and Architecture (~2.5 min)

### Step 5.1: Open a Cashier Shift

**[Screen Movement / Action]:**
Open Point of Sale and select a register, assigned cashier, cashier password, and initial cash fund.

**[Spoken Script]:**
“The POS begins with a validated shift. The system verifies that the register is active and belongs to the Manager's branch, that the cashier belongs to that register, and that the cashier password is correct. It checks for another open shift and then creates or resumes a shift containing the register, cashier, branch, start time, initial fund, and overtime state. This establishes the audit context before the first sale.”

### Step 5.2: Fast Search, Cart, and Totals

**[Screen Movement / Action]:**
Enter a product code or name, select the result, add quantities, and point to subtotal and IVA.

**[Spoken Script]:**
“The scanner field searches by code or name and is restricted to active products with inventory in the current branch. Search results show price and available stock. The cart enforces the branch quantity for every line and recalculates the subtotal and 16-percent IVA automatically. This prevents a cashier from selling a quantity that the local inventory table cannot support.”

### Step 5.3: Atomic Payment and Cash Reconciliation

**[Screen Movement / Action]:**
Select a payment method, confirm the payment, then show expected cash and the close-shift flow.

**[Spoken Script]:**
“Payment processing begins a MySQL transaction. PETKO re-reads stock, validates quantities, creates the sale and detail records, and subtracts each sold quantity from branch inventory. It commits only if every operation succeeds; otherwise the transaction rolls back to preserve consistency. Cash sales update the shift balance, and manual withdrawals are recorded separately. At closing, the Manager enters a password and the physical counted cash. PETKO calculates initial fund plus cash sales minus withdrawals, compares that result with the count, and records Exact, Surplus, or Shortage.”

### Step 5.4: Technical Architecture Closing

**[Screen Movement / Action]:**
Open Sales History or Cash Cuts for a few seconds, then return to the full application view.

**[Spoken Script]:**
“The final evidence is the traceable history. Sales show employee, branch, payment method, amount, status, product lines, quantities, and subtotals; cash cuts show shifts and reconciliation differences. Architecturally, normalized MySQL tables connect roles, users, branches, categories, products, inventory, sales, payment methods, registers, cashiers, shifts, and withdrawals. Composer provides Google authentication, PHPMailer SMTP delivery, and dotenv configuration. CSRF protection, secure sessions, password hashing, role scoping, referential constraints, and database transactions connect the interface to a defensible server-side model.”
