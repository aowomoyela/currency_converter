/* The basic table design assumes that for auditing purposes, we may need to keep records of what rates were in use at any given time. */

create table if not exists exchange_rates(
	exchange_rate_id int unsigned not null auto_increment primary key,
	currency_code char(3) not null,
	rate decimal(10,5) not null, /* Confirm required precision with project lead/contact. */
	current bool not null default 0,
	retrieved timestamp not null,
	deprecated timestamp default null
);

/* Depending on server storage and performance requirements, we may choose to index the `currency_code` and `current` fields. */

alter table exchange_rates add index currency_code(currency_code);

alter table exchange_rates add index current(current);