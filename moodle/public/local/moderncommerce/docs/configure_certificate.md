---
title: Configure Course Certificates
slug: configure_certificate
plugin: mod_coursecertificate
---

# Configure Course Certificates (mod_coursecertificate)

## Overview
This guide helps Moodle admins add and configure Course Certificates for a specific course using mod/coursecertificate, set activity completion, define issuance requirements, and create custom certificate templates.

## Prerequisites
- Role: Course manager or site administrator with `moodle/course:update`.
- Plugin: `mod_coursecertificate` installed and enabled.
- Completion tracking enabled at site and course levels.
- The course ID you want to configure.

## Navigation Path
- Course page → Turn editing on → Add an activity or resource → Course certificate
- Course page → More → Course completion
- Site administration → Plugins → Activity modules → Course certificate (module settings)

## 1. Add Course Certificate to the Course
1. Open the course page.
2. Click "Turn editing on".
3. Click "Add an activity or resource" in the section where you want the certificate.
4. Select "Course certificate" (mod/coursecertificate) and click "Add".
5. Fill out:
   - Name: e.g., "Completion Certificate".
   - Availability/Restrict access: leave default for now.
   - Activity completion: set to "Show activity as complete when conditions are met".
6. Save and return to course.

## 2. Set Up Activity Completion
1. In the course: click More → Course completion.
2. Enable completion tracking and set criteria, e.g.:
   - Require passing grade in final quiz.
   - Require viewing all lessons.
   - Require minimum attendance (if relevant module is present).
3. Save changes.

## 3. Define Requirements to Issue Certificate
1. Edit the Course certificate activity.
2. Configure "Activity completion" for the certificate to depend on the course completion criteria.
3. Use "Restrict access" to gate the certificate until completion conditions are met, e.g.:
   - Add restriction → Activity completion → select required activities completed.
   - Add restriction → Grade → final quiz grade >= passing.
4. Save and test with a student account.

## 4. Create a Custom Certificate Template
1. Open Site administration → General → Certificates → Manage certificate templates, or use **Manage Certificate Templates** from Modern Commerce Settings → Certificates or a bundle/program Advanced Features page.
2. Add fields (learner name, course name, completion date) and branding elements (logo, signature).
3. Position elements and fine-tune fonts and sizes.
4. Preview the certificate.
5. Save template and assign it to the Course certificate activity.

## 5. Configure a Program Final Certificate

1. Open Modern Commerce → Settings → Certificates and optionally select the store-wide default template.
2. Open the program's Advanced Features page and choose a pass policy and pass grade.
3. For Weighted Average or Any Course Pass, select any Must Pass Courses directly beneath Pass Policy. Each course is displayed on its own checkbox row; large lists scroll within the field.
4. Enable the certificate and either use the global template or select a program override.
5. Search for external prerequisite courses when they are required for the final award. Selecting a result closes and clears the dropdown for the next search.
6. Save and test with a learner who has access to every included course.

Program prerequisites and course order never lock included courses. The final award requires every included course and every selected prerequisite to be complete, the pass policy to be satisfied, and every selected must-pass course to pass. When Course Certificate is not installed or upgraded, Modern Commerce continues to work but hides or disables its certificate controls safely.

## Tips & Pitfalls
- Ensure site-level completion tracking is on; otherwise course completion rules won’t apply.
- Keep Restrict access simple; too many conditions can be confusing.
- Test with a non-admin learner to validate issuance.

## Checklist
<ul style="list-style: none; padding-left: 0;">
  <li><label><input type="checkbox"> mod/coursecertificate installed</label></li>
  <li><label><input type="checkbox"> Course certificate added to course</label></li>
  <li><label><input type="checkbox"> Course completion criteria configured</label></li>
  <li><label><input type="checkbox"> Restrict access rules applied</label></li>
  <li><label><input type="checkbox"> Custom template created and assigned</label></li>
  <li><label><input type="checkbox"> Tested with a student account</label></li>
</ul>

## Moodle Docs
- https://docs.moodle.org/en/Certificates
- https://docs.moodle.org/en/Activity_completion
- https://docs.moodle.org/en/Course_completion

## Quick Links
- Open this course: {{courseurl}}
- Course completion settings: {{completionurl}}
- Add activity (Course certificate): Open course and click "Add an activity or resource".
