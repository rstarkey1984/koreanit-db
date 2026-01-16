# 0. 호스트서버에 MySQL 서버가 이미 준비되어 있어야함.

## 0-1. 컨테이너로 전달할 환경변수 설정

### .env 파일 수정
```
DB_HOST=host.docker.internal
DB_PORT=3308
DB_NAME=testdb
DB_USER=test
DB_PASS=test123
DB_CHARSET=utf8mb4
```

---


# 1. 설정

## 1-1. PHP-FPM 이미지 빌드
```
docker build -t custom-php-fpm:8.3-alpine .
```


## 1-2. Docker Compose 설정을 기반으로 컨테이너 관리

Docker Compose 실행:
```
docker compose up -d
```

Docker Compose 중지:
```
docker compose down
```

NGINX 로그확인:
```
docker logs -f web-nginx
```

PHP-FPM 로그확인:
```
docker logs -f web-php
```