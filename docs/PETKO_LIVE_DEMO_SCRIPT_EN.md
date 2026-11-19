# PETKO Live Product Demonstration Script

**Audience:** Potential veterinary, pharmacy, and agricultural business buyers
**Language:** English
**Product:** PETKO — Veterinary Clinic, Pharmacy, and Multi-Branch Management

---

## 1. ZUZU — Home Page Experience

### Step 1.1: Enter Through the PETKO Home

**[Screen Movement / Action]:**
Open `src/php/modulos/home/home.php`. Keep the cursor near the top of the page and let the audience see the complete navigation bar before moving.

**[Spoken Script]:**
“Welcome to PETKO. In the first few seconds, this page communicates one clear idea: this is not just a website, it is an operating platform for veterinary and agricultural businesses. The PETKO brand, clinical styling, dark navigation bar, and persistent language and theme controls establish trust before the buyer even enters a module. This matters because staff need a system that feels professional, clear, and immediately accessible.”

“The top navigation gives us direct access to Home, Features, Pets, Sales, Inventory, Branches, About Us, and sign-in. As a visitor, I do not have to hunt for the platform's main capabilities. PETKO guides me from the brand promise to the operational areas of the business.”

### Step 1.2: Explore the Main Hero and Point-of-Sale Story

**[Screen Movement / Action]:**
Scroll slowly within the large animated Hero area. Point to the headline, the “Get Started” button, and the quick actions for Point of Sale, Inventory, Appointments, and Pets.

**[Spoken Script]:**
“The Hero area says, ‘Your veterinary practice, at the next level.’ The message is deliberately practical. PETKO presents the business around what owners manage every day: charging for consultations and vaccines, controlling medication and food inventory, organizing appointments, and keeping every patient record available.”

“Notice the ‘Get started’ and module shortcuts. If the visitor is signed in, PETKO can send the user directly to the matching dashboard based on the user's role. If the visitor is not signed in, the system opens the login experience. Behind this simple interaction, the application reads the user's session and selects the appropriate administration, manager, or customer destination.”

### Step 1.3: Show the Business Features Grid

**[Screen Movement / Action]:**
Scroll to “Everything your veterinary practice needs” and move across the feature cards one row at a time.

**[Spoken Script]:**
“This is PETKO's operational map. Consultations and medical records, vaccines and parasite control, appointments, grooming and bathing, point of sale, inventory, branches, and users are all visible as connected parts of one platform. That connected presentation is important for a buyer: it reduces the need for separate tools, duplicate spreadsheets, and manual reports.”

“From a clinical and commercial perspective, the same system can support the small day-to-day details that improve customer service. Staff can manage the sale, the product, and the stock context instead of switching between disconnected applications.”

### Step 1.4: Move Through Pets and the Agropecuario Section

**[Screen Movement / Action]:**
Scroll to the animal category tabs and select Dogs, Cats, Rabbits, Birds, Rodents, and Agricultural, briefly stopping on the Agricultural panel.

**[Spoken Script]:**
“The platform speaks to the full customer base. PETKO includes dedicated demonstrations for dogs, cats, rabbits, birds, and rodents, and it also includes agricultural or livestock workflows. For dogs and cats, the focus is vaccines, parasite control, weight, sterilization, and regular reviews. For the agricultural section, the experience includes livestock reviews, laboratory analysis, field visits, and herd control.”

“This is a strong commercial position for the buyer: PETKO is positioned for clinics that serve companion animals and businesses that also operate in the agropecuario space. The interface adapts the message while keeping one recognizable operational platform.”

### Step 1.5: Preview Sales, Inventory, Appointments, and Branches

**[Screen Movement / Action]:**
Scroll through the sales, inventory, appointments, and multi-branch sections. Point to the account summary, product table, appointment card, and branch role cards.

**[Spoken Script]:**
“Here is how PETKO turns the promise into a visible operating story. The sales preview combines the products and services of a transaction. The inventory preview shows products, quantities, and status. The appointment preview organizes today's visits. And the branch section explains the responsibility model: a branch owns its operations, managers oversee the branch, and cashiers execute sales.”

“The system is designed around central information rather than isolated screens. Product and inventory information are stored in the database, so the public catalog and the operating dashboards should read the same source of truth. The structure is especially valuable for owners who manage more than one location.”

### Step 1.6: Transition to the Catalog

**[Screen Movement / Action]:**
Point to the final “Get Started with PETKO” call to action or use the catalog link in the navigation. Click it and pause at the catalog entrance.

**[Spoken Script]:**
“PETKO does not leave the visitor at a marketing page. The clear call to action moves the customer into the catalog and the real business experience. The same visual identity continues, and the next screen changes the role from visitor to shopper or staff member.”

---

## 2. JUAREZ LARA — Product Catalog Experience

### Step 2.1: Arrive at the Catalog Hero

**[Screen Movement / Action]:**
Open `src/php/componentes/catalogo.php`. Start at the top hero, then point to the language button, theme button, panel button, and cart button.

**[Spoken Script]:**
“We are now in the PETKO catalog. This is the main public shopping experience for veterinary, pharmacy, nutrition, and accessory products. The Hero immediately defines the value: medicines, nutrition, and accessories selected for the needs of the animal family.”

“At the top right, customers can change the theme and language, access their panel, or open the cart. The interface is designed for practical use, not as a static brochure. Theme and language controls make the experience comfortable, and the panel button identifies whether the visitor should sign in or go to the dashboard associated with their role.”

### Step 2.2: Review the Catalog Statistics and Enter the Product Grid

**[Screen Movement / Action]:**
Click “Explore Catalog” or scroll to the category sidebar and product counter.

**[Spoken Script]:**
“The catalog dynamically reports the number of products and categories available. Those values come from the database rather than being fixed marketing text. As the buyer browses, the system is reading the active product records and the categories associated with them.”

“The entry into the product section is also clear: the page shows how many items are visible and keeps the search and category controls close to the shopping content.”

### Step 2.3: Use Search and Category Filters

**[Screen Movement / Action]:**
Start with “All Products.” Type a product name or code into the search field. Then select a category such as Medicines, Food, Antiparasitic, Hygiene, or Accessories.

**[Spoken Script]:**
“The search field allows the customer to find a product by name or display text without loading a separate process. The category list is generated from the database categories. At present, PETKO supports the core categories of Medicines, Food, Antiparasitic products, Hygiene, and Accessories.”

“This is one of the platform's convenience benefits: the customer can move quickly from a broad view to a precise selection. It also reduces the number of accidental clicks and helps a buyer find the right product faster. When a filter is selected, the catalog updates the visible list and item count.”

### Step 2.4: Inspect a Product Card

**[Screen Movement / Action]:**
Hover over or click a product image, title, price, stock label, or “Add to Cart” button.

**[Spoken Script]:**
“Each card displays the product image, name, category, price, and available quantity. An available product shows ‘Available,’ low stock is highlighted with the last-pieces warning, and zero stock shows ‘Out of Stock.’ The Add to Cart button is disabled when that product has no stock.”

“This information is built from the central database: product identity and description come from the product table, category information comes from categories, and stock comes from inventory records. In the catalog connection used by this project, a product may use a real image stored in the database or an available local image, and it falls back to a placeholder when no image is available.”

### Step 2.5: Open Details and Add Items to the Cart

**[Screen Movement / Action]:**
Open a product detail modal, close it, and click “Add to Cart.” Open the cart panel and adjust quantities.

**[Spoken Script]:**
“The detail view gives the customer a larger product image and the context they need before making a decision. The product detail is concise, but it is useful: the shopper can confirm the image, price, description, category, and available quantity.”

“Add a product to the cart and notice that the cart counter updates immediately. The cart shows quantities, prices, and the running total. The interface prevents the customer from exceeding the stock value already loaded for that product, which helps prevent an order that cannot be fulfilled.”

### Step 2.6: Demonstrate Checkout and Ticket Generation

**[Screen Movement / Action]:**
Open “Proceed to Payment.” Point to the pay-now option and the pickup-or-cash option. Complete a ticket path if you want to demonstrate the client flow.

**[Spoken Script]:**
“At checkout, PETKO offers two clear paths. The customer can choose ‘Pay Now’ for card or transfer, or choose ‘Reserve and Pay in Store’ for cash pickup. The reservation option communicates that products can be held for 48 hours at the selected branch, while payment happens at pickup.”

“After the choice is confirmed, PETKO generates a digital ticket with a unique folio, date, payment method, status, item list, total, and barcode-style visual. This gives the customer immediate evidence of the order and gives the business a reference for follow-up or clarification.”

“The real database has a central sales and sale-detail structure for completed transactions. The public catalog also contains this polished ticket experience. It is important to distinguish it from the physical counter POS: the public catalog flow generates the customer-facing ticket, while the manager point-of-sale module records a cashier transaction and adjusts branch inventory.”

---

## 3. MIA — Login and Registration Experience

### Step 3.1: Open the Login Page

**[Screen Movement / Action]:**
Open `inicioSesion.php`. Point to the role-login choices, email and password fields, remember-me control if visible, password recovery link, and the register link.

**[Spoken Script]:**
“This is the PETKO access page. It is intentionally simple: the visitor enters an email and password, sees clear feedback, and is sent to the destination associated with their role. The login handler validates the CSRF token, verifies the account status, and uses a secure password check before creating the authenticated session.”

“The system also offers a ‘remember me’ cookie flow for eligible sessions and uses secure cookie settings. The benefit for the buyer is both commercial and operational: returning users spend less time signing in, while sensitive session values remain protected.”

### Step 3.2: Demonstrate Traditional Registration

**[Screen Movement / Action]:**
Navigate to `registro.php`. Move to the terms dialog, then show the first-name, last-name, email, password, confirmation, and terms fields.

**[Spoken Script]:**
“PETKO registration is designed to reduce mistakes before they become account-support problems. The customer must open the Terms and Conditions before accepting them. The form requires all fields, validates the email address, and enforces a strong public-password rule: at least eight characters, one uppercase letter, and one number.”

“The submitted password is hashed and stored with the user's role using the database account structure. The system checks that the email is not already registered and returns immediate feedback through a JSON response. This gives the visitor an efficient experience without leaving or refreshing the form unexpectedly.”

### Step 3.3: Explain Account Activation and Email Delivery

**[Screen Movement / Action]:**
Pause on the registration submit button, then if you have a demonstration account, show the activation email delivered by the administrator workflow.

**[Spoken Script]:**
“For administrator-created managers and cashiers, PETKO uses a 24-hour activation flow. The administrator sends the person a personal activation link, the recipient reviews the terms, and then creates a password to activate the account.”

“That mail is delivered through PHPMailer and SMTP libraries installed through Composer. The Email Service sends an HTML-branded activation message and includes the role and branch in the content. This automation reduces the manual work of sharing credentials and gives each employee a controlled, personal activation process.”

“Google Sign-In has a separate welcome-email flow. For a new Google account, PETKO creates the customer record and sends a welcome message through the same Email Service, with a direct link to the catalog or system.”

### Step 3.4: Demonstrate Google Sign-In

**[Screen Movement / Action]:**
Return to the login page and click the Google Sign-In button. Select or use a valid demonstration account.

**[Spoken Script]:**
“One-click Google authentication removes a second password decision for the customer. PETKO uses the Google API library from Composer and validates the returned ID token. The system checks the email, email-verification state, intended Google client, issuer, and token expiration before linking or creating an account.”

“For an existing account, PETKO links the Google identity safely. If the email and Google identity belong to two different stored accounts, the process stops and reports the conflict. That is exactly the kind of friction-reducing protection a growing business needs: convenient access without silently merging the wrong accounts.”

### Step 3.5: Show Secure Session Routing

**[Screen Movement / Action]:**
After login, point to the destination page and show the user's name or role context. Then optionally demonstrate logout or session timeout behavior.

**[Spoken Script]:**
“After authentication, PETKO regenerates the session identifier and stores the user's identity, role, branch, and account details for role-aware navigation. An Administrator goes to the administration dashboard, a Manager or Cashier is routed to the operational branch dashboard, and a customer is sent to the public catalog or customer area.”

“This reduces abandonment because the user immediately reaches the part of PETKO relevant to their job. It also protects the business because the application then checks the role and branch again during management operations. The home page also uses strict session mode, HTTP-only cookies, CSRF protection for logout, inactivity timeout, and security headers.”

---

## 4. DAVID — Dashboards and Administrative Management

### Step 4.1: Enter the Administrator Dashboard

**[Screen Movement / Action]:**
Sign in with an Administrator account and open `dashboard.php`. Start in the overview and move to the left navigation.

**[Spoken Script]:**
“This is the administrative command center. The first screen summarizes branches, users, products, inventory pieces, inventory value, today's sales count, and today's revenue. Those cards are calculated from the database, so they describe current operations rather than a static presentation.”

“The role-aware navigation gives the Administrator access to the overview, products, inventory, branches, personnel, sales, and reports. This structure allows one person or a management team to see the complete business while keeping operational permissions connected to real roles.”

### Step 4.2: Review Product and Inventory Management

**[Screen Movement / Action]:**
Open Products or Inventory management. Search for a product, change the category or stock filter, then open the create or edit product modal.

**[Spoken Script]:**
“Here the Administrator can create and update products, assign categories, set prices, manage images or image URLs, and assign the product to active branches. The inventory view also exposes stock filters for low stock and out-of-stock items.”

“The database update happens transactionally. The product record is saved, and inventory records are assigned to selected branches. When you update stock, PETKO changes the branch's inventory quantity and stock minimum. The catalog reads that same information, so a change can update what customers see in the public shopping experience.”

“PETKO also prevents deletion of a product while there are remaining units anywhere in inventory. This protects a historical and physical stock context instead of silently removing a product that still has quantities. When deletion is valid, inventory links are removed and existing sale details are preserved by disconnecting the product reference.”

### Step 4.3: Manage Roles and Personnel

**[Screen Movement / Action]:**
Open Personnel or Gerentes section. Create or edit a Manager or Cashier and show the role and branch selectors.

**[Spoken Script]:**
“Administrators assign real roles and branches to personnel. The account model separates Administrators, Managers, and Cashiers. A Manager or Cashier must be associated with a branch, while an Administrator may operate across the platform.”

“When an account is created, PETKO checks for duplicate emails and sends a 24-hour activation link for the selected role and branch. The recipient creates their own password instead of receiving a shared password. This is a stronger security and onboarding model for employee management.”

### Step 4.4: Manage Branches and Operational Scope

**[Screen Movement / Action]:**
Open the Branches view. Select a branch, edit its contact information, or demonstrate that a branch can be marked inactive.

**[Spoken Script]:**
“The branch view gives the owner centralized control over branch name, address, telephone, contact, and status. A branch can be created or updated without losing operational history.”

“If a branch has historical sales, PETKO protects the report by marking the branch inactive instead of deleting it. If a branch has no sales history, the application can remove its inventory, cashier, register, and user assignments in a transaction. This gives the buyer both scalability and data protection.”

### Step 4.5: Review the Sales and Reporting Views

**[Screen Movement / Action]:**
Open Sales, choose a branch and date range if available, then open one sale to inspect its details.

**[Spoken Script]:**
“The sales report brings together the branch, employee, payment method, sale total, status, and date. The buyer can open an individual sale and see its product lines, quantities, and subtotals.”

“The report is backed by normalized sales data: a sale header stores the transaction total and context, and a sale-detail table stores the purchased products and quantities. The reporting view does not depend on reconstructed spreadsheet values; it reads the original transaction structure.”

### Step 4.6: Enter the Manager Dashboard

**[Screen Movement / Action]:**
Switch to a Manager account or open `dashboard_gerente.php`. Point to the restricted overview and branch name.

**[Spoken Script]:**
“The Manager dashboard is scoped to the assigned branch. It shows today's revenue, today's sales, low stock, products assigned to the branch, active registers, and a seven-day sales chart. The Manager can manage that branch's inventory and products, create its registers and cashiers, review sales and cash cuts, and open point-of-sale mode.”

“This branch boundary is a key business benefit. Managers can operate efficiently without gaining access to every other branch. It also helps ownership maintain consistent information across locations while preserving clear operational responsibility.”

---

## 5. RICARDO — Point of Sale, Cash Control, and Key Architecture

### Step 5.1: Open the Cash and Register Management Area

**[Screen Movement / Action]:**
In the Manager dashboard, click “Registers.” Show the active register list and create a register if needed.

**[Spoken Script]:**
“Before making a sale, PETKO gives the Manager a clear register structure. The Manager creates a named register for the branch, then assigns active cashiers to that register. Each register and cashier are stored against the current branch.”

“This separation is important for accountability. A sales operation is not anonymous. PETKO knows the branch, register, cashier, shift, transaction, and payment method. That makes it possible to reconcile activity instead of relying on a notebook or an informal end-of-day total.”

### Step 5.2: Open a Cashier Shift

**[Screen Movement / Action]:**
Click “Point of Sale.” Select a register, select a cashier, enter the cashier password, enter the initial cash fund, and confirm “Open Shift.”

**[Spoken Script]:**
“Now we open a real cashier shift. PETKO asks for the register, cashier, cashier password, and initial cash fund. It validates that the selected cashier belongs to the selected register and branch, verifies the password, and checks whether that register already has another open shift.”

“Once opened, the interface identifies the current register and cashier and starts the shift timer. The same shift is restored after a refresh using a local saved shift reference and a database status check. The Manager can also resume an existing shift for the same cashier.”

### Step 5.3: Search for Products in the POS

**[Screen Movement / Action]:**
Place the cursor in the scanner field. Enter a product code and press Enter, or type a name and select a live result.

**[Spoken Script]:**
“The counter employee works from one fast search field. It accepts a product code or a text search for name and code. The search is scoped to the current branch, and it will not offer active products that have zero stock.”

“That scope is a major advantage for a multi-branch business. If a product is available in one branch but not at this location, the cashier sees the inventory for this branch. A search result includes price and available stock, so the sale is based on the same inventory the business has at that register.”

### Step 5.4: Build the Cart and Verify Totals

**[Screen Movement / Action]:**
Add at least two products. Use the minus, plus, and quantity fields. Point to subtotal, IVA, and total.

**[Spoken Script]:**
“PETKO adds the selected product to the current sale and shows quantity, unit price, line subtotal, and the complete total. The employee can increase or decrease quantities or enter a value directly.”

“The quantity controls enforce the maximum stock available for the branch. If an item is raised beyond stock, the interface stops or adjusts it rather than allowing an impossible sale. PETKO calculates the subtotal and the 16-percent IVA, then presents the final amount to collect.”

### Step 5.5: Process Payment and Confirm the Sale

**[Screen Movement / Action]:**
Select Cash, Card, or Transfer in the payment-method list. Click “Process Payment” and confirm the sale.

**[Spoken Script]:**
“The cashier selects the real payment method and confirms the final amount. PETKO processes the sale inside a database transaction. It re-reads stock for every product, verifies that each quantity is still available, creates the sales header and its product-detail lines, and immediately deducts the sold quantities from the branch inventory.”

“Because this happens as one transaction, a partial sale is not allowed to leave inconsistent data. If stock becomes insufficient or another validation fails, the transaction is rolled back. If everything validates, the sale is marked completed and the public and management views receive the same current inventory information.”

### Step 5.6: Demonstrate the Cash-Flow Controls

**[Screen Movement / Action]:**
Point to the initial fund and expected cash fields. If needed, open “Register Withdrawal” and enter a valid amount.

**[Spoken Script]:**
“The POS also maintains the financial context of the shift. It tracks the initial cash fund, cash sales, manual withdrawals, and expected physical cash. This gives the cashier and Manager a live expected-balance view instead of calculating manually from each transaction.”

“If the physical balance approaches or passes twice the initial fund, PETKO suggests a withdrawal. The Manager or cashier can register that withdrawal with a reason. Withdrawals are separate from sales, but they are included in the end-of-shift expected-cash calculation.”

### Step 5.7: Close the Shift and View the Cash Cut

**[Screen Movement / Action】:**
Click “Close Shift.” Show the initial cash, total sold, withdrawals, and expected cash. Enter the physical count and the Manager password.

**[Spoken Script]:**
“At closing time, the employee enters the physical cash counted and the Manager authorizes the close with their password. PETKO calculates the expected balance, compares it with the physical count, and records a difference as Exact, Surplus, or Shortage.”

“This is a direct operational improvement. It shows exactly where the shift stands, protects the close from an unauthorized click, and creates an auditable history for the Manager. The cash-cut view displays start time, end time, initial fund, total sold, physical count, difference, overtime status, and final shift status.”

### Step 5.8: Review Sales History and Final Architecture

**[Screen Movement / Action]:**
Open the sales history, select a date range, open a completed sale, and briefly open the Cash Cuts table.

**[Spoken Script]:**
“The final view is complete operational evidence. Sales history shows each transaction, employee, payment method, total, and status. Opening a sale reveals the product, quantity, and subtotal. The cash-cut history gives management the reconciliation trail for every shift.”

“PETKO's architecture is purpose-built for this connected value. Centralized MySQL tables organize users, roles, branches, categories, products, inventories, sales, sale details, payment methods, registers, cashiers, shifts, and withdrawals. Branch inventory is a separate quantity record, which allows each location to operate independently while sharing a central platform.”

“Composer packages provide Google authentication, PHPMailer SMTP email, and environment-variable support. The application uses secure sessions, password hashing, CSRF tokens in protected flows, role-aware routing, and database transactions. The combined result is clear: faster checkout, fewer stock mistakes, stronger accountability, and a platform that can scale across multiple branches.”
