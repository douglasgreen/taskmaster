# Design

The is the design of a task reminder program. The tasks are stored in a CSV file with these columns:

-   Task name
-   Done? (0-1)
-   Recurring? (0-1)
-   Recur start (YYYY-MM-DD)
-   Recur end (YYYY-MM-DD)
-   Days of year (YYYY-MM-DD or MM-DD)
-   Days of week (1-7) where 1 is Monday and 7 is Sunday
-   Days of month (1-31 where anything above last day of current month is counted as last)
-   Time of day (00:00 - 23:59)
-   Last date reminded

Tasks are one-time or recurring.

Only one of the days columns is set, so check days of year, days of week, and days of month for the
first non-empty column. An asterisk can be used in any of the day fields to mean every day or the
time field to mean every time.

Multiple values can occur in the days or times columns separated with a pipe character |, for
example, weekdays would be 1|2|3|4|5.

Ranges can occur in the days of week and days of month columns separated with a hyphen character,
for example, a range of weekdays would be 1-5.

Days of year can be specified as YYYY-MM-DD for non-recurring or MM-DD for recurring.

Times allow a 14 minute interval so you should schedule your times at 0, 15, 30, and 45 minutes past
the hour. And you should run the reminder program with a cron every 15 minutes to avoid overlap.

If a reminder has no dates or times scheduled, a nudge email will be sent to remind you it exists.
Only one nudge email is sent per day.

There is a limit of no more than one email reminder per hour.

You can't schedule times without dates.

Here is the algorithm:

1. Go through the list for all tasks that are not done and haven't got a last date reminded of
   today.
2. If the task is recurring, either start or end date can be empty or set. If start date is set,
   reminders are only sent on or after the start date. If the end date is set, reminders are only
   sent on or before the end date.
3. If the task specifies a day, convert it to YYYY-MM-DD form.
4. If the task specifies a time, convert it to HH:MM:SS form.
5. Append time to date to create datetime.
6. If there is no datetime, use the last date reminded plus 30 days with a time of 00:00:00.
7. Render the datetime to seconds using strtotime().
8. If the difference between the datetime seconds and the current time seconds is less than 3 hours,
   send a separate reminder email for each current task and update the last date reminded field to
   the current time.
9. When finished processing all tasks, rewrite them out to the CSV file with the same columns.
