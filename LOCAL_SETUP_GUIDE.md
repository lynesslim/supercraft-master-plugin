# Local Development Setup Guide (Docker Compose)

This document guides developers on how to spin up the local WordPress development environment using `docker-compose` instead of Node/npm tools. This setup is lightweight, runs in isolated Docker containers, and requires zero Node.js/npm dependencies.

---

## 1. Requirements

Make sure you have the following installed on your host system:
* **Docker Desktop** (must be open and running)
* **Git** (for cloning and version control)

---

## 2. Docker Compose Configuration

The project root contains a [`docker-compose.yml`](file:///Volumes/T7/Lyness/SAAS/Supercraft%20Animation%20Plugin/docker-compose.yml) file. This file starts two services:
1. **`db` (MySQL 8.0)**: Stores the WordPress database. Data is persisted in a Docker volume named `db_data`.
2. **`wordpress` (Latest official WordPress image)**: Serves the website. Files are persisted in `wordpress_data`. 

### Key Port & Volume Mounts:
* **Port mapping:** Accessible at `http://localhost:8890` (host port `8890` mapped to container port `80`).
* **Plugin Mount:** Mounts the current plugin directory directly to the container's plugin directory `/var/www/html/wp-content/plugins/supercraft-animation-plugin`. Any changes you make locally are instantly reflected in WordPress.
* **Shared Local Plugins Mount:** Mounts dependency plugins (like Elementor) from `/Users/lynesslim/.supercraft-local-plugins/` directly into WordPress.
* **Themes Mount:** Mounts hello-elementor theme from `./local-themes/` to WordPress.
* **Uploads Mount:** Mounts `./wp-uploads` locally so your test media uploads are saved and persist between container restarts.

---

## 3. Environment Control Commands

Run these commands from the root of your project directory in the terminal:

### Start the Environment
To download the images (first time) and spin up the containers in the background:
```bash
docker compose up -d
```
Once started, open your browser and navigate to **`http://localhost:8890`** to set up or access WordPress.

### Stop the Environment
To stop the running containers without deleting any data:
```bash
docker compose down
```

### Stop and Wipe Data
If you want to completely destroy the environment and wipe out the database volumes to start fresh:
```bash
docker compose down -v
```

---

## 4. Default Database Credentials

If you need to connect a database management tool (like Sequel Ace, TablePlus, or phpMyAdmin) to your local DB:

* **Host:** `localhost`
* **Database Name:** `wordpress`
* **Username:** `wordpress`
* **Password:** `wordpress_password`
* **Root Password:** `root_password`
* **MySQL Port:** Port `3306` inside the Docker network.
