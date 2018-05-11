
# SSS_PTS_TEST

This is a software that can execute SNIA [Solid State Storage (SSS) Performance Test Specification (PTS)](https://www.snia.org/tech_activities/standards/curr_standards/pts) v2.0 test. It performs FIO testing through webpage interface, replacing CLI. Finally uses database to manage historical records.

Each directory is described as follows
  - **web**
  User interface, it can execute test or query result.
  - **test**
  The main processes for testing PTS v2.0.
  - **database**
  Database used by this software.
  - **report**
  Store the result of each test (create it by yourself)

#  
  **Note**
This software contains code derived from [cloudharmony/block-storage](https://github.com/cloudharmony/block-storage) and [Alexandre Bodelot/jquery.json-viewer](https://github.com/abodelot/jquery.json-viewer).