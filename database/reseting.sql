Start transaction;
 
 set foreign_key_checks = 0;

drop table if exists 'user_activities_logs';
drop table if exists 'users';

set foreign_key_checks = 1;

commit;
