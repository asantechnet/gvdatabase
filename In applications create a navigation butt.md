[NO AI, EXPERIMENTAL THINKING]

responsible roles that could be assigned into responsible employees in each case should be changable through Options -> contracts -> responsible roles. only get these employees in the select.

there need to be a way to reject leave requests

in academy plans there need to be certificate number and certificate validation in place.

currency GET requests, filter out the inactive currencies in GET requests. only show all currencies if the request comes from main finance -> currencies page

some kind of hearbeat request (just like the notification heartbeat and the audits heartbeat) would be nice for the current active page. it would be better if we combine the notification hearbeat with this hearbeat and send them in a unified request. so a single hearbeat request checks if there are any changes to the table that the user is viewing (it only envolves main tables. e.g. if user is in commissions page only updates for commissions would be fetched and shown and 1 level of related tables)

icons are missing. my colleage updated icons but now they are missing (they do not appear for me)

check if HR -> Payrolls works completely.

resolve the remote conflicts with local main when a completely "Loss-less" option exists. where it is obvious which code is better for the system keep that code. for the rest YOU MUST REPORT TO ME with Natural language about the conflicts and ask ME explicitely to keep which one. here is a hint: my college mostly worked on HR. I did too. but his work mostly is oreinted on frontend and mine mostly covers the backend. I did some things with frontend as will (mostly on payrolls page). mine is more about the logic, his is most about UI or shallow logic like filteration system, frontend logic or things. mine mostly is about business logic.
IF YOU HAVE SLIGHT DOUPT TO KEEP WHICH CODE JUST REACH OUT TO ME AND EXPLAIN THE CONFLICT IN NATURAL LANGUAGE AND I WILL DECIDE WHICH TO KEEP.

The most conflicts would exist in HR I suppose.


check if 1 hour edit window is working properly by using the playwright cli and acting as a user and interacting with the system
sign-in as real regular user and check if the one-hour edit window is working fine.
there is a 1 hour edit window for employees. all edit actions must be disabled or give error if they are past 1 hour by default. one permission record can override this feature. the override edit window permission or something. check if this feature is correctly in place and is comperhensive and SANE.

see if the bonuses is working completely
see if automatic bonus is working completely.

see if all cronjobs/schedules are working completely

check if contract extending works for employment contracts and other-contracts


see if commissions are working, case commissions and class comissions, accurals and settlements. check if it works end to end. you need to use playwright cli for this task. use your logic reviewer skill and go through the system as three users. sign in as super admin , create teacher employee and admission employee and counselor employee, all as commission-based employees. then sign in as the counselor employee you created. create a private contract, a scholarship contract, a test offer contract, and a academy contract. (select admission employee you created as responsible employee for the contracts). login as the admission employee you created. complete all the cases that are assigned to you and reach them all to completed stage. login as the super admin and create a class and select the teacher employee you created as the teacher. login as the teacher employee you created and reach the class to completed. then login as super admin and find all the commissions for all employees through finance -> commisions. after finding them out you are done. do not take further action and just report to me.
do not remove your track record in the system I need your footprints to check if the system works correctly or not.




FINAL TOUCHES: 
Email delivery. the functionality and label of the active/inactive setting in setup -> settings -> email delivery is misleading. when this option is off no mail service will be used at all. instead it would just give a unified error that says email delivery is not set up or something. fix it

Academy issues:

Now that Academy holds all the new truth. delete the stale branches

remove the university notes in the visa cases page and use the university relationship instead. also add the quick contact just like in visitors/followup list

how does academy accountability works 

Facts
Schedules/cronjobs
HR
Finance
Academy
contracts
cases

there are some complexities in this system about selecting employees per role or per position. tell me which is better, selecting them per role or per position ?



educational brochure

BIG NO NO
<!-- Contracts
When a contract is created an applicant need to be created (applicant only need to be created if an applicant with the same data does not exist) and the service need to be attached to that applicant (service need to be infered from the contract that is opened (private, scholarship, test offer)). right now it seems like this functionality is not correctly in place -->







Applications
In applications nav, create a navigation button for applicants
in applicants page there need to be a way to see the attached services for an applicant
Applicants page already exists

Tasks
no-one except the creator of a comment have the right to edit a comment.
global editability rule need to be in place that only allows editing a comment in a 30m timespan. after it comment is not editable



brochure design. based on the feautres of this project. make a stylish brochure design. you need to use playwright cli + pure HTML + CSS + JS for this task. implement this task in /brochure. you can take screenshots from the impresive parts of the system for displaying in brochure. the term brochure may be a bit misleading, what I want is a 10-page-long comperhensive yet abstract and super stylish and visually pleasing demonstration of the system. final result will be printed with browser print feature. paper-size/page-size is A5. ensure each page is fully used and is in its best state. use retro-art lightly and use stylish designs to perfectly display the system's capabilities. use text but do not use it too much. a short up to 300 character description is good to use for each segment. the core feautres of the system is as follows:
Feautre-Set (it is not up-to-date with latest features):

Applicants:

	Case work, Staging, Portal, Document Gathering, Taskification
	Applicant Portal + Sign-in + Progress Demonstration

HRM:
	Salary Management
	Attendance Management
	Automatic attendance tracking (GEO-fencing)
	Day-to-Day Reporting (Employee Reporting)
	Leave day Management
	Two payment tracking mechanisms, salary and comission

CRM
	Full Visitor, Lead, Customer pipelines
	Conversation Tracking, Noting and Confidence tracking
	Marketer Role with Comissions Management per Customer

Contract Management

	Contract Templates
	Default + Customizable Cover page, Letterhead
	Policy Management
	
Finance
	Invoicing
	Income/Expense tracking
	Assets Management
	Outsourcing

Project Management
	Task Managemnt, 3 Full pages to view tasks from different angles
	Automatic Progress Tracking per Sub-Task Completion

System
	Support Section and Ticketing
	Automatic Bonus Tracking - Task-based + Case-based
	Recurring Events tracking
	Notification System
	Established Auditing System
	Comperehensive RBAC and permission management
	Pending Transactions (for value that is yet to be determined its fate)
	Reporting System (in-page report printing + global reporting for finance, HRM and CRM)
	Overall dashboard + Educational-Specific Dashboard
	Office Management
	Emailing
	Dark/Light Theme
	Dari/Pashto/English languages
	
Settings
	Comperhensive Customization Accessability
	User,Permission,Role Management with OTP and smooth onboarding
	
Et Cetera
