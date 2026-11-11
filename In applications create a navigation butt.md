[NO AI, EXPERIMENTAL THINKING]

some kind of hearbeat request (just like the notification heartbeat and the audits heartbeat) would be nice for the current active page. it would be better if we combine the notification hearbeat with this hearbeat and send them in a unified request. so a single hearbeat request checks if there are any changes to the table that the user is viewing (it only envolves main tables. e.g. if user is in commissions page only updates for commissions would be fetched and shown and 1 level of related tables)



see if commissions are working, case commissions and class comissions, accurals and settlements. check if it works end to end. you need to use playwright cli for this task. use your logic reviewer skill and go through the system as three users. sign in as super admin , create teacher employee and admission employee and counselor employee, all as commission-based employees. then sign in as the counselor employee you created. create a private contract, a scholarship contract, a test offer contract, and a academy contract. (select admission employee you created as responsible employee for the contracts). login as the admission employee you created. complete all the cases that are assigned to you and reach them all to completed stage. login as the super admin and create a class and select the teacher employee you created as the teacher. login as the teacher employee you created and reach the class to completed. then login as super admin and find all the commissions for all employees through finance -> commisions. after finding them out you are done. do not take further action and just report to me.
do not remove your track record in the system I need your footprints to check if the system works correctly or not.





plan-implement these:

responsible roles that could be assigned into responsible employees in each case should be changable through Options -> contracts -> responsible roles. only get these employees in the select.

there need to be a way to reject leave requests

currency GET requests, filter out the inactive currencies in GET requests. only show all currencies if the request comes from main finance -> currencies page

copy the correct overlay system from academy revamp to main branch. applications page have some issues right now in main branch in dark theme

remove the university notes in the visa cases page and use the university relationship instead. also add the quick contact just like in visitors/followup list

FINAL TOUCHES: 
Email delivery. the functionality and label of the active/inactive setting in setup -> settings -> email delivery is misleading. when this option is off no mail service will be used at all. instead it would just give a unified error that says email delivery is not set up or something. fix it

Applications
In applications nav, create a navigation button for applicants
in applicants page there need to be a way to see the attached services for an applicant
Applicants page already exists

Tasks
no-one except the creator of a comment have the right to edit a comment.
global editability rule need to be in place that only allows editing a comment in a 30m timespan. after it comment is not editable

Visa Cases Page
there need to be a quick contact way for universities and countries just like in visiors/followup list
remove the University notes for a university in university applications -> tracked applications and instead add the quick contact buttons for it














the multi-tenant worktree is a lot behind the main branch. be extra careful and sync it with main branch, use the constraints in docs/facts/branch-mirroring.md (which is accessable through multi-tenant worktree)






cleanup the project from doc files. markdown (.md) files are scattered around the project. I want them cleaned up and all. @resources/images directory seems like a stale vuexy directory which is of no help for my system. cleanup all ties to this directory and clean the directory itself as will.

instead of the doc plans I need a unified docs/facts (which already exists. but I need it to be more comperhensive). it need to cover facts about all the project, specially:
Schedules/cronjobs
HR
Finance
Academy
contracts
cases

create a directory for each one of these and explain each in details.

remove the md files directly located under /docs. remove md files in /docs/ai. remove md files in root. remove /issues directory entirely. remove /hr-issues, /fin-issues, commission-issues, other-contracts-issues, 

also put /docs into .gitignore 








check if this is code error or any migration command to run:
SQLSTATE[23000]: Integrity constraint violation: 1052 Column 'service' in where clause is ambiguous (Connection: mysql, Host: 127.0.0.1, Port: 3306, Database: educational_office_main, SQL: select detail.country_id, applicants.current_stage_id, COUNT(*) as total from `applicants` inner join ((select `applicant_id`, `country_id`, 'scholarship' as service from `scholarship_applicant_details` where `deleted_at` is null) union all (select `applicant_id`, `country_id`, 'private' as service from `private_applicant_details` where `deleted_at` is null)) as `detail` on `detail`.`applicant_id` = `applicants`.`id` and `detail`.`service` = `applicants`.`service` where `service` != academy and `detail`.`country_id` is not null and `applicants`.`deleted_at` is null group by `detail`.`country_id`, `applicants`.`current_stage_id`)
