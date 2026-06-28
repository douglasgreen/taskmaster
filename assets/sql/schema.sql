CREATE DATABASE nurdsite_task_manager;


USE nurdsite_task_manager;


CREATE TABLE recurring_tasks (
    id INT(11) NOT NULL auto_increment,
    title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
    details TEXT COLLATE utf8_unicode_ci,
    recur_start date default NULL,
    recur_end date default NULL,
    days_of_year TEXT COLLATE utf8_unicode_ci,
    days_of_week VARCHAR(100) COLLATE utf8_unicode_ci default NULL,
    days_of_month VARCHAR(100) COLLATE utf8_unicode_ci default NULL,
    time_of_day VARCHAR(500) COLLATE utf8_unicode_ci default NULL,
    last_reminded_at timestamp NULL default NULL,
    created_at DATETIME NOT NULL,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    PRIMARY KEY (id),
    KEY idx_recurring_tasks_last_reminded_at (last_reminded_at)
) engine = innodb default charset = utf8 COLLATE = utf8_unicode_ci;


CREATE TABLE task_groups (
    id INT(11) NOT NULL auto_increment,
    name VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    PRIMARY KEY (id),
    UNIQUE KEY uq_task_groups_name (name)
) engine = innodb default charset = utf8 COLLATE = utf8_unicode_ci;


CREATE TABLE tasks (
    id INT(11) NOT NULL auto_increment,
    group_id INT(11) NOT NULL,
    title VARCHAR(255) COLLATE utf8_unicode_ci NOT NULL,
    details TEXT COLLATE utf8_unicode_ci,
    due_date date default NULL,
    created_at DATETIME NOT NULL,
    updated_at timestamp NOT NULL default current_timestamp ON UPDATE current_timestamp,
    PRIMARY KEY (id),
    KEY idx_tasks_group_id (group_id),
    CONSTRAINT fk_tasks_task_groups FOREIGN KEY (group_id) REFERENCES task_groups (id) ON DELETE CASCADE
) engine = innodb default charset = utf8 COLLATE = utf8_unicode_ci;
