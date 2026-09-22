# Implementation Plan — Educational Visa Agency Management Platform

## 1. Objective

Transform the existing software-solutions management platform into a complete management system for an agency specializing in educational admissions and educational visas.

The implementation must preserve useful existing functionality while changing the business model so that the system revolves around:

**Customers → Applicants → Educational Services → Contracts → Application/Visa Workflow → Tasks → Financial Transactions → Approvals → Income/Expense/Salary → Bonuses → Reporting**
`
The new implementation must not be treated as a collection of unrelated CRUD additions.

The system should behave as a connected business platform where information entered in one area is reused automatically everywhere else.

The primary business object is the **Applicant / Case**, because the agency's main operational question is:

> "Where is this customer's educational admission and visa process, who is responsible for it, what has been sold, what has been signed, what remains to be done, and how much money has been generated or spent?"
> 

---

# 2. Existing System Context

The existing application contains areas similar to:

- Home
- Contracts
- CRM
- Invoice
- Finance
- HR
- Tasks
- Options
- Setup

The existing Applicants interface also contains concepts such as:

- All Applicants
- Operation List
- Final List
- Cancel List
- Canceled Contract

The existing system currently has relatively flat contract templates.

The new implementation must retain the existing platform's working architecture wherever practical, but introduce a domain model specifically designed for an educational-visa agency.

Do not unnecessarily rewrite working modules.

Prefer:

**Extend → refactor where necessary → migrate → integrate**

rather than:

**delete existing system → rebuild everything**

---

# 3. Core Business Model

Use the following conceptual model as the foundation.

## 3.1 Visitor

A person who has interacted with the agency but is not yet necessarily a customer.

A visitor may eventually become a customer.

## 3.2 Customer

The agency's commercial/client record.

A customer may have:

- one or more contracts, if applicable
- one or more applicants
- one or more services
- financial activity
- documents
- tasks
- notes
- employees assigned to them

## 3.3 Applicant

The operational case/person whose educational admission and visa process is being managed.

Do not treat Applicant as merely another customer list.

An applicant must represent an active business process.

An applicant should be associated with:

- customer
- responsible employee
- destination country
- education level
- selected university
- services
- contracts
- tasks
- application progress
- financial activity
- status history

## 3.4 Educational Service

A product/service sold or provided to a customer/applicant.

Examples may include:

- scholarship-related service
- educational placement service
- test
- test preparation
- application service
- other packages supported by the agency

Services must be attributable to employees for commission/bonus purposes.

## 3.5 Contract

A legally/businessually significant contract generated from a versioned contract template.

A contract must retain the exact template/version from which it was generated.

Changing a template in the future must never unexpectedly change historical contracts.

---

# 4. Required Implementation Order

Implement in the following order.

Do not jump directly into dashboards or UI redesign.

## Phase 0 — Analyze the Existing Application

Before changing code:

1. Inspect the existing project structure.
2. Identify frontend architecture.
3. Identify backend architecture.
4. Identify database technology.
5. Identify authentication system.
6. Identify current roles and permissions.
7. Identify existing CRM entities.
8. Identify existing contract entities.
9. Identify existing finance entities.
10. Identify existing employee/HR entities.
11. Identify current Tasks implementation.
12. Identify existing Reports/Dashboard implementation.
13. Identify current Applicants implementation.
14. Identify migrations/seeding strategy.
15. Identify all database foreign-key relationships.
16. Identify APIs/services/controllers currently responsible for these entities.
17. Identify reusable components and patterns.

Do not duplicate existing functionality when a suitable component/service/repository already exists.

Before implementation, produce a dependency map showing:

**Existing entity → new behavior → required schema change → impacted modules**

---

# 5. Phase 1 — Establish the New Domain Model

Create or refactor the database model first.

The implementation AI must establish proper relationships before building screens.

## 5.1 Master Data Entities

Create CRUD entities for:

### Countries

Fields:

- id
- name
- code
- status/is_active
- created_at
- updated_at

Use this entity across the application instead of hard-coded country lists.

### Education Levels

Fields:

- id
- name
- description
- status/is_active
- sort_order
- created_at
- updated_at

Education levels must be dynamic.

Never hard-code education-level options inside business logic or frontend components.

Any relevant screen must load education levels from the database.

### Languages

Fields:

- id
- name
- code
- status/is_active
- created_at
- updated_at

Languages must be dynamic because contracts and other business entities depend on language.

### Currencies

Reuse the existing currency entity if one exists.

Do not create a duplicate currency table unless the existing implementation is incompatible.

### Package Types

Fields:

- id
- name
- description
- service_type
- status/is_active
- created_at
- updated_at

Support package types for scholarships/services.

Design this generically enough that future service types can use package types without redesigning the schema.

### Universities

Fields:

- id
- name
- country_id
- city
- website
- description
- status/is_active
- created_at
- updated_at

Use country relationship rather than storing country text.

Prepare the design so university-related admissions can later contain:

- admission requirements
- deadlines
- tuition information
- application notes

Do not overbuild those features unless the current business requirements need them.

---

# 6. Phase 2 — Contract Architecture

This is one of the most important changes.

The current system apparently treats a contract template as one flat template.

Replace that conceptual model with:

**Contract Type → Contract Template → Contract Template Version**

## 6.1 Contract Type

Examples:

- Educational Service
- Scholarship Service
- Test Service
- Visa Service
- Other Agency Service

Fields:

- id
- name
- description
- status/is_active

CRUD required.

## 6.2 Contract Template

Fields:

- id
- contract_type_id
- name
- description
- status/is_active
- created_by
- created_at
- updated_at

CRUD required.

A template represents a logical contract document.

## 6.3 Contract Template Version

Fields:

- id
- contract_template_id
- language_id
- version_number
- content
- status
- is_current
- created_by
- created_at
- updated_at

Rules:

1. A template may have multiple versions.
2. A template may exist in multiple languages.
3. A language may have independent version numbering.
4. Historical versions must never be silently overwritten.
5. A new version must be created when content changes in a legally meaningful way.
6. A contract must store the exact template version used at generation time.

Example:

Educational Service Contract

- English / Version 1
- English / Version 2
- Russian / Version 1
- Persian / Version 1

Do not copy the same contract record multiple times just to represent language differences.

---

# 7. Phase 3 — Contract Generation

When a user creates a contract:

1. Select customer/applicant.
2. Select contract type.
3. Select contract template.
4. Select language.
5. System selects the active applicable template version.
6. Generate/populate contract.
7. Store the exact template version ID.
8. Store the rendered/generated contract snapshot.
9. Allow authorized users to manage the contract according to permissions.
10. Historical contract contents must remain stable even when templates are updated later.

If template Version 2 becomes active tomorrow, yesterday's Version 1 contract must still render exactly as it did when signed/generated.

This is mandatory for legal and audit integrity.

---

# 8. Phase 4 — Applicant / Visa Case Management

Make Applicant Management the operational center of the system.

## 8.1 Applicant entity

Suggested fields:

- id
- customer_id
- responsible_employee_id
- country_id
- university_id
- education_level_id
- language_id
- application_date
- status
- current_stage
- notes
- created_at
- updated_at

Use nullable foreign keys where the information may not be known at the beginning of the process.

## 8.2 Applicant Status

Create controlled statuses rather than arbitrary text.

Recommended initial status flow:

**New**

→ **Consultation**

→ **Documents Required**

→ **Documents Complete**

→ **University Selection**

→ **University Application**

→ **Admission Pending**

→ **Admission Received**

→ **Contract/Service Active**

→ **Visa Preparation**

→ **Visa Submitted**

→ **Visa Processing**

→ **Visa Approved**

→ **Completed**

Alternative terminal or exceptional statuses:

- Cancelled
- Rejected
- Withdrawn

The business may rename statuses, but the architecture must support an ordered pipeline.

---

# 9. Applicant Stage History

Do not only store the current stage.

Create an applicant stage/history mechanism containing:

- applicant_id
- old_stage
- new_stage
- changed_by
- changed_at
- note/reason

This provides a historical answer to:

> Who moved this applicant from one stage to another and when?
> 

The history is also useful for reports and management.

---

# 10. Applicant Dashboard / Case View

Each applicant should have a central detail page.

The page should visually connect:

### Applicant information

- customer
- employee
- destination country
- education level
- university
- language

### Progress

Show the applicant's current stage prominently.

### Timeline

Show stage changes and important events chronologically.

### Contracts

Show related contracts.

### Services

Show purchased/assigned services.

### Tasks

Show outstanding operational work.

### Finance

Show related payments, income, expenses and pending transactions.

### Documents

Reuse the existing document system if one exists.

### Notes / activity

Show relevant staff activity.

The employee should be able to understand the entire case from this page without opening ten unrelated modules.

---

# 11. Applicant Workflow

Implement actions that correspond to actual agency work.

Example:

1. Lead/customer enters agency.
2. Customer becomes customer.
3. Applicant/case is created.
4. Employee is assigned.
5. Destination country selected.
6. Education level selected.
7. Candidate universities recorded.
8. Service/package sold.
9. Contract created from correct language/versioned template.
10. Tasks are generated.
11. Documents are collected.
12. Application submitted.
13. Admission status tracked.
14. Visa process begins.
15. Financial transactions continue through approval process.
16. Applicant reaches final/approved/completed state.
17. Reports update automatically.

The implementation should connect these operations.

---

# 12. Phase 5 — Scholarships

Create a dedicated Scholarship entity.

Suggested fields:

- id
- customer_id
- applicant_id
- employee_id
- country_id
- currency_id
- education_level_id
- language_id
- package_type_id
- date
- fee
- status
- created_at
- updated_at

Use `applicant_id` where the scholarship is connected to a specific application.

Keep it nullable only if the business genuinely allows scholarship records before an applicant exists.

The employee relationship is required because employee performance and bonuses depend on services sold/provided.

---

# 13. Clarify Scholarship Semantics in Implementation

The implementation team must verify whether "scholarship" means:

A. an actual scholarship opportunity/funding offered by a university, or

B. an agency service/package that helps a customer obtain education/scholarship opportunities.

The database/UI should not assume these are identical concepts.

If the business means an agency service, label the UI accordingly and model it as a service/product.

If the business means actual scholarship opportunities, add scholarship-provider/university/award-related fields later.

Do not mix those concepts silently.

---

# 14. Phase 6 — Test Offers

Create or extend the Test/Test Offer module.

Each sellable test/service must be connected to:

- employee
- customer
- applicant where applicable
- price
- currency
- status
- date

The purpose is to allow the business to answer:

> Which employee sold which service to which customer/applicant and for how much?
> 

That data must later feed bonus calculations.

---

# 15. Phase 7 — Employee Sales and Bonuses

Do not implement bonuses as unrelated hard-coded calculations for each table.

Create a generic commission/bonus relationship.

At minimum support:

- employee_id
- customer_id
- applicant_id
- service/entity type
- service/entity ID
- source amount
- bonus amount
- bonus rule/source
- status
- created_at

Supported sources initially:

- contract
- scholarship
- test offer

This architecture must be extensible to future services.

The employee who sold/provided the service must remain traceable even if the underlying record is later viewed from finance/reporting.

---

# 16. Phase 8 — Pending Transactions and Approval Workflow

Create a dedicated `PendingTransaction` concept.

Fields:

- id
- transaction_type
- reason
- amount
- currency_id
- customer_id
- applicant_id if applicable
- employee_id
- requested_by
- approver_id
- approval_status
- rejection_reason
- requested_at
- approved_at
- rejected_at
- posted_at
- created_at
- updated_at

Potential transaction types:

- income
- expense
- salary

Do not post directly to official financial tables from normal employee workflows.

---

# 17. Transaction Approval Lifecycle

The workflow must be:

**Draft/Pending**

→ **Submitted**

→ **Under Review**

→ **Approved**

→ **Posted**

or:

**Submitted**

→ **Rejected**

A rejected transaction must remain auditable.

Do not delete rejected transactions simply to make the interface look clean.

The system needs to know:

- who created it
- who reviewed it
- who approved/rejected it
- when it happened
- why it was rejected

---

# 18. Posting Approved Transactions

Only an approved transaction may become an official accounting entry.

After approval:

### Income

Create/update official Income entry.

### Expense

Create/update official Expense entry.

### Salary

Create/update official Salary entry.

Store the relationship back to the originating pending transaction.

This must prevent duplicate posting.

Example protection:

A pending transaction with ID 123 can only produce one official accounting record.

---

# 19. Income

Income must support optional relationships.

Suggested fields:

- id
- amount
- currency_id
- date
- reason
- customer_id nullable
- visitor_id nullable
- applicant_id nullable
- employee_id nullable
- source_type
- source_id
- pending_transaction_id
- created_at
- updated_at

`customer_id` and `visitor_id` must be optional.

Examples:

Office-related income may have neither.

A customer payment may reference customer.

A visitor deposit may reference visitor.

An applicant-related payment may reference applicant.

---

# 20. Expense

Use equivalent structure.

Suggested fields:

- id
- amount
- currency_id
- date
- reason
- customer_id nullable
- visitor_id nullable
- applicant_id nullable
- employee_id nullable
- source_type
- source_id
- pending_transaction_id
- created_at
- updated_at

Support customer-related expenses without requiring every expense to belong to a customer.

---

# 21. Active Contract Business Rule

The requirement that customer-related expenses/services concern customers with at least one active contract should be implemented as a business rule where relevant.

Do not duplicate "active customer" into another field unless necessary.

Derive active-customer status from actual contract/application state.

Example:

A customer is eligible for customer-specific operational expenses when:

**customer exists AND at least one relevant contract is active**

The exact definition of "active contract" should be centralized in one service/policy rather than duplicated in multiple controllers.

---

# 22. Phase 9 — One-Hour Editing Rule

Implement a centralized editing policy.

For entities covered by the rule:

**Employee can edit own record for up to one hour after creation.**

After one hour:

**Normal employee → cannot edit**

**Manager/Admin/authorized role → may edit according to permission**

Use server-side enforcement.

Do not rely only on hiding the Edit button in the frontend.

Suggested policy:

`editable_until = created_at + 1 hour`

Check:

- authenticated user
- role/permission
- record ownership
- creation timestamp
- workflow status

Example:

Employee A creates pending expense at 10:00.

Employee A may edit until 11:00.

At 11:01:

- employee cannot edit
- authorized manager may edit if permission allows
- approved/posting workflow restrictions still apply

The system should clearly tell employees when a record is locked.

---

# 23. Phase 10 — Permissions and Roles

Do not simply add permissions to existing screens.

Redesign permissions around the new business workflow.

## Applicants

- View applicants
- Create applicants
- Edit applicants
- Delete/cancel applicants
- Change applicant stage
- Assign employee
- View applicant financials
- View applicant contracts
- View applicant services

## Contracts

- View contracts
- Create contracts
- Edit contracts
- Delete/cancel contracts
- View contract templates
- Create templates
- Edit templates
- Create template versions
- Activate template versions

## Scholarships

- View scholarships
- Create scholarships
- Edit scholarships
- Delete scholarships

## Tests

- View test offers
- Create test offers
- Edit test offers
- Delete test offers

## Universities

- View universities
- Create universities
- Edit universities
- Delete universities

## Master Data

For each:

- countries
- education levels
- languages
- package types
- currencies if managed here

provide View/Create/Edit/Delete as appropriate.

## Finance

- View pending transactions
- Create pending transactions
- Edit pending transactions
- Submit transaction
- Approve transaction
- Reject transaction
- Post transaction
- View income
- View expense
- View salary
- Edit official finance records

## Bonuses

- View own bonuses
- View team bonuses
- Manage bonuses
- Approve bonuses if applicable

---

# 24. Segregation of Duties

Where practical, prevent a normal employee from approving their own financial transaction.

Preferred rule:

**Creator ≠ Approver**

unless an administrator role explicitly has permission to override this.

This protects the business financially and creates a more trustworthy audit trail.

---

# 25. Phase 11 — Auditability

For financial and contract-related functionality, maintain audit information.

At minimum:

- created by
- updated by
- created at
- updated at

For important state changes:

- changed by
- previous value/state
- new value/state
- timestamp
- reason when applicable

Important examples:

- applicant stage changed
- contract version selected
- transaction approved/rejected
- income posted
- expense posted
- employee assignment changed

---

# 26. Phase 12 — Tasks Integration

The current Tasks module should not remain isolated.

Applicant workflow events should create or associate tasks.

Examples:

When applicant reaches:

**Documents Required**

Create task:

"Collect required applicant documents"

When reaching:

**University Application**

Create:

"Prepare and submit university application"

When reaching:

**Visa Preparation**

Create:

"Prepare visa documentation"

Tasks should contain:

- applicant
- customer
- assigned employee
- due date
- status
- priority

Do not automatically create duplicate tasks every time a screen is opened.

Use workflow events/state transitions.

---

# 27. Phase 13 — Applicant Pipeline UI

Create a visual workflow/pipeline.

Recommended columns:

1. New
2. Consultation
3. Documents Required
4. Documents Complete
5. University Selection
6. University Application
7. Admission Pending
8. Admission Received
9. Visa Preparation
10. Visa Submitted
11. Visa Processing
12. Visa Approved
13. Completed

Provide filters for:

- employee
- country
- university
- education level
- language
- date
- status

The existing Operation List / Final List / Cancel List should map to the new workflow rather than remain arbitrary lists.

For example:

**Operation List**

= active applicants requiring operational work

**Final List**

= completed/finalized applicants

**Cancel List**

= cancelled applicants

**Canceled Contract**

= applicants/contracts with cancelled contracts

Preserve these business concepts where the client still uses them, but implement them using proper statuses and relationships.

---

# 28. Phase 14 — Financial Connection to Applicants

Every relevant financial record should be traceable back to the business activity that caused it.

Example:

Applicant → Contract → Payment

should allow:

Applicant page

→ Financial section

→ Payment

→ Income record

→ Original pending transaction

→ Approver

Similarly:

Applicant → Service → Employee → Bonus

must be traceable.

This "reverse navigation" is critical for management.

---

# 29. Phase 15 — Dashboard Redesign

The dashboard must be rebuilt based on the new agency workflow.

Do not simply add a few cards to the old dashboard.

Create sections such as:

## Applicant Pipeline

- Total active applicants
- New
- In university application
- Admission pending
- Visa preparation
- Visa processing
- Approved
- Completed
- Cancelled

## Financial Summary

- Pending transactions
- Approved transactions
- Income
- Expenses
- Salaries
- Net financial result

## Sales

- Contracts sold
- Scholarships/services sold
- Tests sold
- Revenue by service

## Employee Performance

- Applicants assigned
- Services sold
- Contracts sold
- Bonuses generated

## Countries

- Applicants by country
- Completed applications by country

## Universities

- Applicants by university
- Applications in progress

## Attention Required

- overdue tasks
- applicants stuck in a stage
- pending approvals
- missing documents
- contracts requiring attention

---

# 30. Phase 16 — Reporting

Build reports around actual business decisions.

Required reports should include at minimum:

### Applicant Report

Filter by:

- country
- education level
- university
- employee
- stage
- date

### Employee Performance Report

Show:

- applicants assigned
- services sold
- contracts sold
- revenue attributed
- bonus amount

### Contract Report

Show:

- contract type
- language
- template/version
- employee
- customer
- applicant
- status

### Scholarship/Service Report

Show:

- country
- education level
- package type
- employee
- revenue
- date

### Financial Report

Show:

- income
- expenses
- salaries
- pending transactions
- approved transactions
- rejected transactions
- totals by date/currency/category

### University Report

Show:

- applicants
- applications
- admission outcomes
- visa outcomes where available

---

# 31. Phase 17 — Navigation Redesign

Use the current navigation as the foundation but reorganize it around the new business.

Recommended navigation:

## Home

Dashboard

## Applicants

- All Applicants
- Active / Operation List
- Final List
- Cancelled
- Applicant Pipeline

## Contracts

- Contracts
- Contract Templates
- Template Versions
- Contract Types

## CRM

- Customers
- Visitors
- Employees

## Services

- Scholarships
- Tests
- Packages
- Package Types
- Universities

## Finance

- Pending Transactions
- Income
- Expenses
- Salaries
- Bonuses

## HR

Existing HR functionality

## Tasks

Existing Tasks functionality plus applicant integration

## Reports

- Applicants
- Financial
- Employees
- Services
- Universities
- Contracts

## Setup

- Countries
- Education Levels
- Languages
- Currencies
- Package Types
- Permissions
- Roles

Do not duplicate the same master-data functionality in multiple menu areas.

---

# 32. Phase 18 — Data Relationships

The implementation AI must ensure the following relationships are supported.

### Customer

Customer can have:

- applicants
- contracts
- services
- scholarships
- test offers
- income
- expenses
- tasks

### Applicant

Applicant belongs to:

- customer
- employee
- country
- education level

Applicant may have:

- university
- language
- contracts
- scholarships
- TOEFL/IELTS tests
- services
- tasks
- financial transactions
- stage history

### Contract

Contract belongs to:

- customer
- applicant where applicable
- contract template/version
- employee where applicable

### Contract Template

Template belongs to:

- contract type

Template has many:

- template versions

Template version belongs to:

- language

### Scholarship

Scholarship belongs to:

- customer
- applicant where applicable
- employee
- country
- education level
- language
- package type
- currency

### Transaction

Transaction may belong to:

- customer
- applicant
- employee

Transaction is approved by:

- approver

Approved transaction generates:

- income OR expense OR salary

---

# 33. Phase 19 — API / Backend Architecture

Do not implement business rules only in controllers.

Create reusable domain services/policies for:

- applicant state transitions
- contract version selection
- contract generation
- transaction approval
- transaction posting
- editing-window validation
- commission/bonus calculation
- permission checks
- active-customer checks

This prevents the same rules from being implemented differently in different parts of the application.

---

# 34. Phase 20 — Frontend Rules

Frontend must reflect backend rules.

Examples:

If an employee cannot edit a record:

- hide/disable edit action
- show lock state
- show remaining edit time when useful

If a transaction is pending:

- show approval state
- show approver
- show rejection reason if rejected

If an applicant has reached Visa Processing:

- show that stage prominently
- surface relevant tasks

If a contract was generated from Version 2:

- show template/version information to authorized users

The UI should help users understand *why* an action is unavailable.

---

# 35. Phase 21 — CRUD Standards

All CRUD screens should follow the existing application's design system.

Every new CRUD entity must support, where applicable:

- list
- search
- filter
- sort
- create
- edit
- view
- delete/deactivate
- pagination
- validation
- permission checking

For master data, prefer deactivate/archive over destructive delete when referenced by historical records.

Example:

Do not hard-delete a country that appears in historical applicant records.

Instead:

`is_active = false`

Historical data remains valid.

---

# 36. Phase 22 — Validation Rules

Implement relational validation.

Examples:

A scholarship cannot reference a nonexistent customer.

An applicant's country must reference an existing country.

A contract must reference an actual template version.

An active contract must reference valid customer/applicant data.

A pending transaction must contain required fields according to its transaction type.

Approval cannot occur without an authorized approver.

A posted transaction cannot be posted twice.

A historical contract cannot change merely because the source template changes.

Employee bonus attribution must reference a valid employee.

---

# 37. Phase 23 — Migration Strategy

Do not destroy existing data.

Create a migration strategy.

First:

1. Backup database.
2. Create new migrations.
3. Add new tables/columns.
4. Populate required master data.
5. Map existing contracts into the new contract structure.
6. Map existing applicants into the new applicant model where possible.
7. Preserve IDs/relationships where safe.
8. Validate counts before/after migration.
9. Run application tests.
10. Only then remove/deprecate obsolete fields.

Where an existing field cannot be reliably mapped, preserve the old value rather than silently inventing data.

---

# 38. Phase 24 — Backward Compatibility

Existing working functionality must continue to work unless intentionally replaced.

After every major migration verify:

- login
- CRM
- customers
- employees
- existing contracts
- finance
- invoices
- tasks
- permissions
- reports

Do not assume that a successful database migration means the system is working.

---

# 39. Phase 25 — Test Strategy

Create automated and manual tests for the business workflows.

## Applicant

Test:

- create applicant
- assign employee
- change stage
- record history
- cancel applicant
- complete applicant

## Contract

Test:

- create template
- create English version
- create second English version
- create another language version
- generate contract
- update template
- verify historical contract remains unchanged

## Finance

Test:

- employee creates transaction
- employee edits within one hour
- employee cannot edit after one hour
- unauthorized employee cannot approve
- authorized approver approves
- approved transaction becomes income/expense/salary
- same transaction cannot be posted twice
- rejected transaction retains audit trail

## Bonuses

Test:

- employee sells contract
- bonus generated
- employee sells scholarship/service
- bonus generated
- employee sells test
- bonus generated
- reporting totals match

## Permissions

Test every important role against:

- view
- create
- edit
- delete
- approve
- post

---

# 40. Phase 26 — Business Acceptance Tests

Do not consider the project complete merely because CRUD screens exist.

The implementation must pass these end-to-end scenarios.

## Scenario A — New Student

1. Create visitor.
2. Convert to customer.
3. Create applicant.
4. Assign employee.
5. Select country.
6. Select education level.
7. Select university.
8. Create service/scholarship.
9. Generate appropriate contract in selected language.
10. Create operational tasks.
11. Move applicant through stages.
12. Verify dashboard updates.

## Scenario B — Contract Versioning

1. Create contract type.
2. Create template.
3. Create English Version 1.
4. Generate contract.
5. Create English Version 2.
6. Generate another contract.
7. Confirm first contract still contains Version 1 content.
8. Generate Russian Version 1.
9. Verify language selection works independently.

## Scenario C — Financial Approval

1. Employee creates expense.
2. Expense is pending.
3. Employee may edit during one-hour window.
4. Employee submits transaction.
5. Employee cannot approve own transaction if segregation rule applies.
6. Authorized approver approves.
7. Expense becomes official.
8. Dashboard and financial reports update.
9. Applicant/customer relationship remains visible.

## Scenario D — Bonus

1. Employee sells service.
2. Service is linked to customer/applicant.
3. Employee attribution stored.
4. Bonus calculation occurs.
5. Bonus visible in employee report.
6. Management can trace bonus back to source sale.

## Scenario E — Applicant Progress

1. Applicant starts at New.
2. Required tasks appear.
3. Employee advances applicant.
4. Stage history is recorded.
5. Dashboard counts update.
6. Relevant tasks are visible.
7. Applicant eventually reaches Visa Approved.
8. Final report reflects successful outcome.

---

# 41. Phase 27 — Dashboard Data Integrity

Every dashboard metric must be calculated from authoritative underlying data.

Do not store duplicate counters unless absolutely necessary.

For example:

"Active Applicants"

should be derived from applicant status/state rules rather than manually incremented counters.

"Total Income"

should derive from approved/posted financial records rather than a manually maintained dashboard number.

This prevents dashboard drift.

---

# 42. Phase 28 — Performance and Scalability

The design should anticipate growth.

Add database indexes for high-use relationships such as:

- customer_id
- applicant_id
- employee_id
- country_id
- university_id
- education_level_id
- contract_template_id
- contract_template_version_id
- status
- created_at
- transaction status

List pages must use pagination.

Reports with large datasets should use optimized queries rather than loading the entire dataset into the browser.

---

# 43. Phase 29 — UX Principles

The interface should be optimized for agency employees, not database administrators.

A user should not have to remember which module contains the information.

Important information should be reachable from the Applicant page.

For example:

From Applicant:

**Contracts → Services → Tasks → Finance → Timeline**

From Customer:

**Applicants → Contracts → Services → Finance**

From Employee:

**Assigned Applicants → Sales → Bonuses → Tasks**

This creates a connected business experience.

---

# 44. Phase 30 — Search

Implement global or contextual search where practical.

Search should find applicants/customers by relevant identifiers such as:

- name
- phone
- email
- applicant ID
- customer ID
- contract number

The exact fields depend on the existing CRM.

Search results should show enough contextual information to distinguish similar people.

---

# 45. Phase 31 — Status and State Design

Avoid arbitrary strings spread throughout the codebase.

Statuses must be defined centrally.

For each workflow status define:

- display name
- internal key
- ordering
- whether active
- whether terminal
- allowed next states

This allows the business workflow to evolve without rewriting the application everywhere.

---

# 46. Phase 32 — Role Examples

Suggested roles:

## Administrator

Full system access.

## Manager

Can:

- manage applicants
- manage contracts
- manage services
- approve transactions
- view reports
- manage employees within authorized scope

## Employee / Agent

Can:

- manage assigned customers
- manage assigned applicants
- create services/sales
- create contracts according to permissions
- create pending transactions
- edit own records within one-hour window
- view own performance

## Finance

Can:

- view pending transactions
- approve/post where permitted
- manage income
- manage expenses
- manage salaries
- view financial reports

## HR

Existing HR responsibilities plus employee information.

Do not hard-code these roles if the existing authorization system supports configurable roles.

---

# 47. Phase 33 — Permissions Must Be Object-Aware

Whenever possible, permission checks should consider:

- role
- permission
- ownership
- assignment
- status
- editing window

Example:

An employee may have:

`applicant.edit = true`

but only for:

`assigned_to_current_employee = true`

This is more useful than global edit access.

---

# 48. Phase 34 — Reporting Relationships

Reports must support drill-down.

Example:

Dashboard:

**Visa Processing: 37 applicants**

Click.

→ list of 37 applicants

Click applicant.

→ case page

Click financial transaction.

→ pending/approved transaction

Click source.

→ service/contract

This is the type of connected reporting the client will actually benefit from.

---

# 49. Phase 35 — Implementation Discipline

The AI implementing this project must follow these rules:

1. Inspect before modifying.
2. Reuse existing architecture.
3. Do not duplicate entities.
4. Do not create hard-coded master data.
5. Do not put business rules only in frontend code.
6. Do not bypass permissions.
7. Do not allow approval workflow to be bypassed.
8. Do not destroy historical contract versions.
9. Do not silently delete historical financial data.
10. Maintain auditability.
11. Update reports whenever underlying business data changes.
12. Test each workflow end-to-end.
13. Keep migrations reversible where practical.
14. Keep old functionality working until the replacement is validated.

---

# 50. Recommended Implementation Sequence

Execute actual development in this exact sequence.

### Step 1

Analyze existing codebase and produce architecture/dependency map.

### Step 2

Design database changes and migrations.

### Step 3

Implement/master-data foundation:

- Countries
- Education Levels
- Languages
- Universities
- Package Types

### Step 4

Implement Contract Types.

### Step 5

Implement Contract Templates.

### Step 6

Implement Contract Template Versions and language-specific versioning.

### Step 7

Refactor Contract creation/generation to use template versions.

### Step 8

Implement Applicants and applicant status workflow.

### Step 9

Implement applicant stage history.

### Step 10

Connect Applicants to Customers, Employees, Countries, Education Levels and Universities.

### Step 11

Build Applicant Case/Detail page.

### Step 12

Connect Tasks to Applicant workflow.

### Step 13

Implement Scholarships/Services.

### Step 14

Implement Test Offers.

### Step 15

Implement employee attribution and bonus model.

### Step 16

Implement Pending Transactions.

### Step 17

Implement approval workflow.

### Step 18

Implement one-hour editing policy.

### Step 19

Connect approved transactions to Income/Expense/Salary.

### Step 20

Add optional customer/visitor/applicant relationships to financial records.

### Step 21

Implement permissions and role updates.

### Step 22

Implement audit logging.

### Step 23

Update navigation.

### Step 24

Redesign Applicant list/pipeline.

### Step 25

Redesign Applicant Case screen.

### Step 26

Update dashboards.

### Step 27

Update reports.

### Step 28

Perform data migration.

### Step 29

Run regression tests on all old functionality.

### Step 30

Run end-to-end business acceptance tests.

### Step 31

Fix inconsistencies between permissions, backend rules and UI.

### Step 32

Perform final usability and data-integrity review.

---

# 51. Definition of Done

The project is complete only when all of the following are true:

## Data

All required entities exist and relationships are correct.

## CRUD

Required entities have complete CRUD behavior.

## Contracts

Contracts support:

**type + template + language + version**

and historical contracts preserve their original version.

## Applicants

Applicants represent a complete educational/visa case and have a visible progression workflow.

## Services

Scholarships/services/tests can be associated with employees and customers/applicants.

## Finance

Financial transactions go through approval before becoming official income, expense or salary records.

## Bonuses

Employee bonuses can be traced to the underlying contract/service/test activity.

## Permissions

Permissions reflect:

- roles
- ownership
- approval authority
- one-hour editing window
- sensitive financial operations

## Tasks

Tasks are connected to applicant work.

## Reports

Reports reflect the new entities and workflows.

## Dashboard

Dashboard accurately reflects current operational and financial data.

## Auditability

Important changes can be traced to users and timestamps.

## Historical Integrity

Historical contracts, financial records and workflow history are not silently rewritten.

## User Experience

An employee can manage a student's case without manually reconstructing information from unrelated modules.

---

# 52. Final Product Concept

The finished system should feel like an **Educational Visa Agency Management / CRM / Operations / Finance platform**, not like a generic software-management system with extra tables.

The core relationship should be:

**Customer**

↓

**Applicant / Case**

↓

**Country + Education Level + University**

↓

**Services / Scholarship / Tests**

↓

**Contract using Language-Specific Versioned Template**

↓

**Tasks + Documents + Application/Visa Progress**

↓

**Transactions**

↓

**Approval**

↓

**Income / Expense / Salary**

↓

**Employee Bonus**

↓

**Reports + Dashboard**

Every major module should connect to this chain.

The implementation AI should make reasonable business-oriented design decisions when a requirement is ambiguous, but it must not invent contradictory business rules. When ambiguity materially affects accounting, contracts, permissions, or legal history, preserve flexibility in the schema and flag the assumption in the implementation notes.

The target outcome is not merely a system that stores data.

The target outcome is a system that allows the agency to answer, at any moment:

**Who is this applicant?**

**What are they trying to study and where?**

**What stage are they in?**

**Who is responsible?**

**What have we sold them?**

**What contract did they sign?**

**Which exact contract version and language was used?**

**What work remains?**

**What money has moved?**

**What still needs approval?**

**What bonus does the employee earn?**

**And what is the agency's overall performance?**

That is the business model the implementation should be built around.
