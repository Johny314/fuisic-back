# Changelog

## [4.2.0](https://github.com/Johny314/fuisic-back/compare/v4.1.0...v4.2.0) (2026-09-26)


### Возможности

* repetitions API with daily queue and idempotent card review ([2e4ed1d](https://github.com/Johny314/fuisic-back/commit/2e4ed1d10bbf33dc267cce4d374165c99cb5455a))

## [4.1.0](https://github.com/Johny314/fuisic-back/compare/v4.0.2...v4.1.0) (2026-09-26)


### Возможности

* add FSRS-5 scheduler and per-card review state ([882b2fc](https://github.com/Johny314/fuisic-back/commit/882b2fcd199ce3087f90925196cd9fcc982e7ed2))
* question types, options, images and points for test tasks ([b59fa9e](https://github.com/Johny314/fuisic-back/commit/b59fa9e7feb22e4ac10d810fcd66baed9534711b))
* user settings for timezone and repetitions ([43285de](https://github.com/Johny314/fuisic-back/commit/43285dea2827558b615714bf51aaa4c52b5d913b))

## [4.0.2](https://github.com/Johny314/fuisic-back/compare/v4.0.1...v4.0.2) (2026-09-26)


### Исправления

* hide the correct answer of a task from non-editors ([3c0be74](https://github.com/Johny314/fuisic-back/commit/3c0be7471099716f3d065475fb39f7d9bbb32a27))

## [4.0.1](https://github.com/Johny314/fuisic-back/compare/v4.0.0...v4.0.1) (2026-09-26)


### Исправления

* add remember_token column to users ([374cf77](https://github.com/Johny314/fuisic-back/commit/374cf773bf3e805441cef8166d9fa1dcbebdb85d))
* create the public/storage link in make setup-local ([a72f92e](https://github.com/Johny314/fuisic-back/commit/a72f92e3c9602c49c6f360f01025afc0b01e0fb9))
* reset email verification when a user changes email ([dbf384c](https://github.com/Johny314/fuisic-back/commit/dbf384c0cccb27c6d8de3404d87899cb77851a64))
* run the Laravel scheduler in docker compose ([2003a9e](https://github.com/Johny314/fuisic-back/commit/2003a9eb2d1c69820b19ecb8af57b9026b4c3d49))

## [4.0.0](https://github.com/Johny314/fuisic-back/compare/v3.7.1...v4.0.0) (2026-09-26)


### ⚠ BREAKING CHANGES

* поле `user_type` исчезло из `GET /me`, `GET /user`, `GET /user/{user}`, ответов `POST/PUT /user` и схемы OpenAPI `User`; в запросах оно игнорируется. Роль определяется по `roles`/`permissions` из `GET /me`. Требует fuisic-auth с `feat!: stop returning user_type from /me`.

### Возможности

* drop the legacy user_type field in favour of roles ([07f3a19](https://github.com/Johny314/fuisic-back/commit/07f3a1938111587dcc90e9f665ea0dccb3eb1f3e))
* staff audit log with admin viewer and 12-month retention ([d1d5fbd](https://github.com/Johny314/fuisic-back/commit/d1d5fbdd1f734ff44af21e68b299d5c2e3f1bd86))

## [3.7.1](https://github.com/Johny314/fuisic-back/compare/v3.7.0...v3.7.1) (2026-09-26)


### Исправления

* keep the admin panel session with Laravel 13 password hashes ([8aaf1ed](https://github.com/Johny314/fuisic-back/commit/8aaf1ed670909f32e175164d8fa9263a7329408a))

## [3.7.0](https://github.com/Johny314/fuisic-back/compare/v3.6.0...v3.7.0) (2026-09-26)


### Возможности

* admin panel by permissions, role editor and role assignment ([4d55bcc](https://github.com/Johny314/fuisic-back/commit/4d55bcc7401947ac5ea868b84bb8c8bd8b7c4e49))

## [3.6.0](https://github.com/Johny314/fuisic-back/compare/v3.5.0...v3.6.0) (2026-09-26)


### Возможности

* user blocking with reason, term and history ([3ff62e3](https://github.com/Johny314/fuisic-back/commit/3ff62e3ac1f431d14de1358613b238c9ae6894ca))

## [3.5.0](https://github.com/Johny314/fuisic-back/compare/v3.4.0...v3.5.0) (2026-09-26)


### Возможности

* parent and child accounts with login by username ([254958e](https://github.com/Johny314/fuisic-back/commit/254958e01e6787c7686db82ede7b484094ad1774))

## [3.4.0](https://github.com/Johny314/fuisic-back/compare/v3.3.0...v3.4.0) (2026-09-26)


### Возможности

* teacher verification applications ([00289e2](https://github.com/Johny314/fuisic-back/commit/00289e2142b1809a4936d375f228b9d3b36bcebc))

## [3.3.0](https://github.com/Johny314/fuisic-back/compare/v3.2.0...v3.3.0) (2026-09-26)


### Возможности

* access checks on Laravel policies and permissions ([50ca9d1](https://github.com/Johny314/fuisic-back/commit/50ca9d1028753b09d8243a29855db4581e31e648))

## [3.2.0](https://github.com/Johny314/fuisic-back/compare/v3.1.1...v3.2.0) (2026-09-26)


### Возможности

* roles and permissions on spatie/laravel-permission ([a26ff4c](https://github.com/Johny314/fuisic-back/commit/a26ff4cf3779906c1695d917394d4efebaeff57e))

## [3.1.1](https://github.com/Johny314/fuisic-back/compare/v3.1.0...v3.1.1) (2026-09-26)


### Документация

* release badges instead of hand-maintained versions. ([276b08a](https://github.com/Johny314/fuisic-back/commit/276b08a5d89a13063caac54b320d74f60e0252a2))
