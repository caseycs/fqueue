create table fqueue (
  id int (10) unsigned not null auto_increment,
  class varchar(50)  not null,
  queue varchar(50)  not null,
  params varchar(1000) not null ,
  retry_timeout int(10) not null,
  retries_remaining int(10) not null,
  status enum('new', 'in_progress', 'success', 'fail_permanent','fail_temporary', 'error', 'timeout') not null,
  next_retry_time datetime,
  create_time datetime not null,
  start_time datetime,
  finish_time datetime,
  primary key(id),
  KEY `queue_next_retry_time_retries_remaining` (`queue`, `retries_remaining`, `next_retry_time`),
  KEY `finish_time_retries_remaining` (`finish_time`, `retries_remaining`)
);
