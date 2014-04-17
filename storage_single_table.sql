create table fqueue (
  id int (10) not null unsigned auto_increment,
  create_time datetime not null,
  start_time datetime,
  finish_time datetime,
  class varchar(50)  not null,
  queue varchar(50)  not null,
  params varchar(1000) not null ,
  retries int(10) not null,
  status enum('new', 'in_progress', 'success', 'fail_permanent','fail_temporary', 'error', 'timeout') not null,
  primary key(id)
);
