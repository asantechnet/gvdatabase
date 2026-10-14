Settings/setup -> company info, for some parts the system is using native browser alerts, remove them and use vuexy template alerts instead 

Remove announcements from HR navGroup and create a icon-button for it in header, with a vuexy tooltip saying what is it for

there need to be proper should 

staff list, deletion alert, there need to be a fine-grained message ensuring user understands the consequenses of deleting an employee, the same goes for users page. a fine grained user deletetion message need to be in place. those are important 

move staff salaries from HR to finance navGroup

staff list, salary month is not appearing good, the date icon is going off the component vertically

staff list page, there department and report to fields are appearing as duplicated see if this is per logic or is an error


visitor form, if a user with role of counselor tried to add a visitor the visitor's counselor becomes auto-filled with that counselor and immutable 

Applicants relationship need to be in the applications page as will. 

commissions need to be in place 

This is how commissions need to work. if a contract has the name of the employee in it and it reached completed stage then the associated employee gets a percentage of that service. 

if an employee was marked as commission-based it need a commission list that is per each service. these both need to be editable in staff creation/edit form for each employee 

when a contract reached completed stage the system need to check if the contract's associated employeesare commission-based or not. if they are depending on the service, emoloyee's comission for that service, service fee, etc. the system need to account for those employees' commission 

for this to work you need to bring the commissions page back to life from previous commits. settlements, accruals are correctly in place in this page but I'm not sure if backend logic that was in place for commissions still applies to new architecture. please have a look at that as will and only reuse it if it's still viable for the new structure







other contracts do not have any counterparty.

academy applications do not need an associated employee name

dashboard need to be changed to include the new data about tasks/contracts. visa reports and global reports need to be changed to be more focused on educational/finance/hrm/crm etc as will

Duplicated fields in service contracts need to be moved to Applicants specially those that Generally relate to Applicant itself 


extract the academy data from options -> contracts -> educational data page into its own page. under contracts subpage. the design need to just like 

rename the all applicants page under /applicants fully to applications.
applications need a payment status: paid/partially paid/

visa case page and applications page. link the applicant name to CRM -> Applicants and clicking it automatically navigates to applicants and opens that specific applicant's phone contact menu. alternatively implement the same design in applicants CRM, relationship and quick contacting via email and phone, make it look good

Loans need to be changed to payment requests. an admission/counselor can send payment requests and finance manager can approve and print them. the final print is depending on income/expense state of the transaction. if income it prints differently and if expense it prints a bit different 












## Task
task remaining: contracts-extend-versioning-print-plan.md

Google drive connection would be cool in educational DB. there would be a section in settings for storage where the user could link their google drive account to the system and from then on the system would use that drive as storage.

Applicants page. remove the stages notation from top and merge the stages into the tabs in the 




# Task Scope
Tasks and sub-tasks systems in educational system

# Task
Tasks page. Personal board is for employees and need to be isolated and no one employee would be able to see or other employees personal tasks. ensure this system is in place

Contracts pages, unify the page layout for these pages. make the service contracts look exactly like other contracts page (the add button and filtration icon and print icon appear in the data-table itself.

CRM -> Applicants page, move the print icon next to filter icon and reduce the width of the search component and make it fixed.

contract template, when signature is loaded from settings. the signature form for our company goes down, it just need to stay where it is.

Settings, please make the email delivery section completely optional, edit frontend and backend to accomplish this.

settings, there is no way to delete signature/logo files.
# Income/Expense
Please add date range filters to filtration menu.
Ensure payment overrides are only accessed by an individual permission/RBAC, and proper warnings are in place for editing a record. /incomes 
# Pending Transactions / Loans
Loans need to be printed just like Receipts

















### Task 
Please assess these comments and make a full plan and save it in /docs page and record your progress in that file as will

Visa Reports page, print template looks very bad, get inspired by other templates, including the overall reports printing and fix this printing page. the current template appears depending on theme, in dark them it appears dark in final print !


View popup component, footer, remove this text from footer of popup:
Showing business-friendly details. Internal database references are hidden.

Employment Contracts need to be extendable just like academy contracts

cancelled contracts page, instead of rename the client column to counterparty

service contracts do need a cancel action button as will all of them. other contracts need that as will. the required intend is the same, they just need to be moved to cancelled contracts page while all of their related date retained and viewable from that page.

all contracts in all pages, cancelled contracts still will exist in the contract pages they belong to. they only need to get a cancelled label and the print action button for them is not available, only view need to be available.

Finance -> reports, General Reports page, the print template's generated-at date  is not normalized and human readable and localized. please fix


