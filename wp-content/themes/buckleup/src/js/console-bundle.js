// Console (portal) barrel — bundles the 11 Student/Instructor/Admin console
// modules into a single lazy chunk. main.js dynamically imports this ONLY when a
// [data-console] shell is present (see inc/console.php), so the ~console JS never
// ships to marketing pages. initConsole() runs each module's init in the EXACT
// same order main.js used to boot them statically; every module is idempotent and
// bails when its own [data-*] hooks are absent, so calling them all is safe.

import { initConsole as initConsoleShell } from './modules/console.js';
import { initConsoleReviews } from './modules/console-reviews.js';
import { initConsoleProfile } from './modules/console-profile.js';
import { initConsoleTags } from './modules/console-tags.js';
import { initConsoleSchedule } from './modules/console-schedule.js';
import { initConsoleAvailability } from './modules/console-availability.js';
import { initConsoleStudents } from './modules/console-students.js';
import { initConsoleAdminStudents } from './modules/console-admin-students.js';
import { initConsoleGraduates } from './modules/console-graduates.js';
import { initConsoleAdminReviews } from './modules/console-admin-reviews.js';
import { initConsoleAvatar } from './modules/console-avatar.js';

export function initConsole(root = document) {
  initConsoleShell(root);
  initConsoleReviews(root);
  initConsoleProfile(root);
  initConsoleTags(root);
  initConsoleSchedule(root);
  initConsoleAvailability(root);
  initConsoleStudents(root);
  initConsoleAdminStudents(root);
  initConsoleGraduates(root);
  initConsoleAdminReviews(root);
  initConsoleAvatar(root);
}
