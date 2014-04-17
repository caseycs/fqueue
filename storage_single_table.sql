create table fqueue (
  id int (10) not null unsigned auto_increment,
  create_time datetime not null,
  start_time datetime,
  finish_time datetime,
  class varchar(50)  not null,
  queue varchar(50)  not null,
  params varchar(1000) not null ,
  retries_remaining int(10) not null,
  status enum('new', 'in_progress', 'success', 'fail_permanent','fail_temporary', 'error', 'timeout') not null,
  primary key(id),
  KEY 'queue_retries_count_status_create_time' ('queue', 'retries_count', 'status', 'create_time'),
  KEY 'finish_time_status' ('finish_time', 'status')
);
