# 사전 준비 사항
- git
- mysql
- docker

# 소스코드 github에서 다운받기

이 저장소는 **강의안(md) + 소스코드**가 함께 들어 있다.
WSL 환경에서는 **소스코드(`web-docker`)만** 받기 위해
Git의 **sparse-checkout** 기능을 사용한다.

```bash
git clone --filter=blob:none --no-checkout https://github.com/rstarkey1984/koreanit-db.git && cd koreanit-db && git sparse-checkout init --cone && git sparse-checkout set web-docker && git checkout HEAD -- web-docker
```
```bash
rm -rf .git
```


## 컨테이너로 전달할 환경변수 설정

### `web-docker/demo/.env` 파일 수정
```
DB_HOST=host.docker.internal
DB_PORT=3308
DB_NAME=testdb
DB_USER=test
DB_PASS=test123
DB_CHARSET=utf8mb4
```

---


# PHP-FPM 이미지 빌드
```
docker build -t custom-php-fpm:8.3-alpine .
```

---

# 스프링부트 API 서버

```
cd demo
```

## Docker Compose 설정을 기반으로 컨테이너 관리

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