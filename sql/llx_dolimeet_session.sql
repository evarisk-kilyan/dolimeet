-- Copyright (C) 2021-2023 EVARISK <technique@evarisk.com>
--
-- This program is free software: you can redistribute it and/or modify
-- it under the terms of the GNU General Public License as published by
-- the Free Software Foundation, either version 3 of the License, or
-- (at your option) any later version.
--
-- This program is distributed in the hope that it will be useful,
-- but WITHOUT ANY WARRANTY; without even the implied warranty of
-- MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
-- GNU General Public License for more details.
--
-- You should have received a copy of the GNU General Public License
-- along with this program.  If not, see https://www.gnu.org/licenses/.

CREATE TABLE llx_dolimeet_session(
	rowid         integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref           varchar(128) DEFAULT '(PROV)' NOT NULL,
	ref_ext       varchar(128),
    entity        integer DEFAULT 1 NOT NULL,
    label         varchar(255),
    date_creation datetime NOT NULL,
	tms           timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	import_key    varchar(14),
    status        integer NOT NULL,
    date_start    datetime NOT NULL,
    date_end      datetime NOT NULL,
    duration      integer,
    type          varchar(128),
	content       text,
	note_public   text,
	note_private  text,
	model_pdf     varchar(255),
	last_main_doc varchar(255),
	document_url  varchar(255),
    fk_project    integer,
    fk_contrat    integer,
    fk_soc        integer,
	fk_user_creat integer NOT NULL,
	fk_user_modif integer
) ENGINE=innodb;