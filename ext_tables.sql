#
# Table structure for table 'sys_event_store'
#
CREATE TABLE sys_event_store (
	event_stream varchar(128) NOT NULL,
	event_categories varchar(256) DEFAULT NULL,
	event_id varchar(36) NOT NULL,
	event_version int(11) unsigned NOT NULL,
	event_name varchar(256) NOT NULL,
	event_date TIMESTAMP(6) NOT NULL,
	aggregate_id varchar(36) DEFAULT NULL,
	data mediumtext,
	metadata text,

	PRIMARY KEY (event_stream, event_version)
);
