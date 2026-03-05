# Student Management System

A comprehensive system to manage roles, users, curriculum, enrollment, grading, and attendance for schools.

---

## Table of Contents

1. [Role-Based Permission](#role-based-permission)  
2. [User Management](#user-management)  
3. [Grade and Section Management](#grade-and-section-management)  
4. [Curriculum Management](#curriculum-management)  
5. [Enrollment System](#enrollment-system)  
6. [Scheduling System](#scheduling-system)  
7. [Grading System](#grading-system)  
8. [Attendance](#attendance)  
9. [Teacher Side](#teacher-side)  
10. [Student Side](#student-side)  
11. [Docker Setup](#docker-setup)  

---

## 1. Role-Based Permission

- Define roles: Admin, Teacher, Student  
- Assign permissions per role (view, create, edit, delete)  
- Restrict access to pages/features based on role  

---

## 2. User Management

- Create, edit, deactivate user accounts  
- Assign roles to users  
- Reset passwords  
- Basic user profile (name, email, role)  

---

## 3. Grade and Section Management

- Create grade levels (Grade 7, Grade 8, etc.)  
- Create sections per grade (Section A, B, etc.)  
- Assign a room or capacity to a section  
- Activate/deactivate grades and sections  

---

## 4. Curriculum Management

- Create subjects (name, code, description)  
- Assign subjects to a grade level  
- Set units/hours per subject  
- Activate/deactivate subjects  

---

## 5. Enrollment System

- Enroll a student to a grade and section  
- Set an enrollment period (school year, semester)  
- View and manage enrolled students per section  
- Prevent duplicate enrollments  

---

## 6. Scheduling System

- Assign subjects to a section  
- Assign a teacher per subject  
- Set time slots (day, start time, end time)  
- Detect and flag schedule conflicts  

---

## 7. Grading System

- Set grading components (written, performance, quarterly)  
- Input grades per student per subject  
- Auto-compute final grade based on set weights  
- Flag failing grades  

---

## 8. Attendance

- Record daily or per-subject attendance  
- Mark as Present, Absent, or Late  
- View attendance summary per student  
- Flag students with excessive absences  

---

## 9. Teacher Side

- View assigned subjects and sections  
- Input and update student grades  
- Record attendance for their classes  
- View their own schedule  

---

## 10. Student Side

- View personal profile and enrollment details  
- View class schedule  
- View grades per subject  
- View attendance record  

---

## 11. Docker Setup

### Prerequisites

Make sure the following are installed on your machine before proceeding:

- [Docker](https://www.docker.com/products/docker-desktop)
- [Docker Compose](https://docs.docker.com/compose/install/)

---

### Services

| Service    | Container Name        | Port        | Description            |
|------------|-----------------------|-------------|------------------------|
| app        | schoolsys_api_app     | `8000:80`   | PHP + Nginx (combined) |
| postgres   | schoolsys_api_db      | `5432:5432` | PostgreSQL 16 Database |
| adminer    | schoolsys_api_adminer | `8080:8080` | Adminer DB UI          |

---

### Setup Steps

#### 1. Clone the Repository

```bash
git clone <your-repository-url>
cd <your-project-folder>
```

#### 2. Create the Environment File

Copy the example env file and fill in your values:

```bash
cp .env.example .env
```

Then open `.env` and set the following variables:

```env
DB_DATABASE=your_database_name
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password
```

#### 3. Build and Start the Containers

```bash
docker compose up -d --build
```

> The `-d` flag runs the containers in detached mode (background).  
> The `--build` flag forces a rebuild of the app image.

#### 4. Verify Running Containers

```bash
docker compose ps
```

All three containers should have a status of `running`.

#### 5. Access the Services

| Service   | URL                   |
|-----------|-----------------------|
| API / App | http://localhost:8000 |
| Adminer   | http://localhost:8080 |

---

### Useful Commands

#### Set permissions
```bash
chown -R www-data:www-data /var/www
chmod -R 775 /var/www/storage /var/www/bootstrap/cache
```

#### Stop all containers
```bash
docker compose down
```

#### Stop and remove volumes
```bash
docker compose down -v
```

#### View container logs
```bash
docker compose logs -f
```

#### View logs for a specific service
```bash
docker compose logs -f app
```

#### Access the app container shell
```bash
docker exec -it schoolsys_api_app bash
```

#### Access the PostgreSQL container shell
```bash
docker exec -it schoolsys_api_db psql -U ${DB_USERNAME} -d ${DB_DATABASE}
```