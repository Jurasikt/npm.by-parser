DROP TABLE IF EXISTS "methods";
CREATE TABLE "methods" (
  "method_id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "method_email" text NOT NULL,
  "method_cookies" text NULL
);

DROP TABLE IF EXISTS "sqlite_sequence";
CREATE TABLE sqlite_sequence(name,seq);

DROP TABLE IF EXISTS "task";
CREATE TABLE "task" (
  "id" integer NOT NULL PRIMARY KEY AUTOINCREMENT,
  "task_departure_station" integer NOT NULL,
  "task_arrival_station" integer NOT NULL,
  "task_date_start" text NULL,
  "task_date_end" text NULL,
  "task_status" integer NULL,
  "task_time" text NULL,
  "task_cache" text NULL,
  "method_id" integer NOT NULL,
  FOREIGN KEY ("method_id") REFERENCES "methods" ("method_id") ON DELETE NO ACTION ON UPDATE NO ACTION
);
