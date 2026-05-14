# Changelog

All notable changes to this project will be documented in this file. See [commit-and-tag-version](https://github.com/absolute-version/commit-and-tag-version) for commit guidelines.

## [1.0.0](https://github.com/projek-xyz/container/compare/v0.7.0...v1.0.0) (2026-05-14)


### ⚠ BREAKING CHANGES

* Raised minimum PHP requirement to 8.0

* to fix symfony-insight issues ([#81](https://github.com/projek-xyz/container/issues/81)) ([3517823](https://github.com/projek-xyz/container/commit/35178235c5542e424fe6b7c5ba67c3193acafbda))


### Features

* add `prepare` workflow to run on PR ([2b4a6d3](https://github.com/projek-xyz/container/commit/2b4a6d31bfe39f351ead4b82e8be3647ee571ec8))
* add php 8.3 test ([#62](https://github.com/projek-xyz/container/issues/62)) ([823a0ed](https://github.com/projek-xyz/container/commit/823a0ed4b201cd4775d6db5db787346441de0548))
* bump minimum php version to `7.4` ([e9e8714](https://github.com/projek-xyz/container/commit/e9e8714f75c76fd944eaf444f05cdc3bf6ce71d3))
* **ci:** add read contents permission on github actions ([9e4ad95](https://github.com/projek-xyz/container/commit/9e4ad95ca72188ed27db66129b4068669abf835f))
* **ci:** add static analysis job ([ea27827](https://github.com/projek-xyz/container/commit/ea27827b7f44451b7e97ffdb7c78c6a4137d5e55))
* **ci:** add weekly test schedule on github actions ([1e193f1](https://github.com/projek-xyz/container/commit/1e193f1d072b05a5d9744bd66f5f8537e018203e))
* **ci:** add write pull-request permission on github actions ([3c97cb7](https://github.com/projek-xyz/container/commit/3c97cb7055a099718e2eac91ee86fffeb7557233))
* **ci:** messing around with specific job permissions ([d2be246](https://github.com/projek-xyz/container/commit/d2be246f2dc5457fa4e6df24aa4d29f8235f2507))
* switch back to `projek-xyz/actions` workflows ([#64](https://github.com/projek-xyz/container/issues/64)) ([0a92a6f](https://github.com/projek-xyz/container/commit/0a92a6f99383fbe2fb3fc823d7faa68159d13094)), closes [projek-xyz/actions#1](https://github.com/projek-xyz/actions/issues/1)
* use composable release workflow ([f137d19](https://github.com/projek-xyz/container/commit/f137d19bdf2e1fae0a3b7d6e9e57f9ab79d03dc0))


### Bug Fixes

* **ci:** fix missing cache-dir ([#79](https://github.com/projek-xyz/container/issues/79)) ([f379dc9](https://github.com/projek-xyz/container/commit/f379dc9d1b48944f70cd18c35068d7e1f7a5bc9d))
* fix codeclimate config for phpmd ([cc7a65f](https://github.com/projek-xyz/container/commit/cc7a65fd6023a368f7f6bc500635bbb6ab90896b))
* fix Container::make() returns existing instance issue ([a162c97](https://github.com/projek-xyz/container/commit/a162c974191676f89880168785e12a7abd90e19c))
* fix linting issue ([f669f30](https://github.com/projek-xyz/container/commit/f669f30f436bfefefd09b6bc5d4c3e7546856693))
* fix silly method name typo ([de2f773](https://github.com/projek-xyz/container/commit/de2f7738220254af8a26b486169f822be2625d96))

## [0.7.0](https://github.com/projek-xyz/container/compare/v0.6.0...v0.7.0) (2021-06-02)


### ⚠ BREAKING CHANGES

* apply changes of 993ce5a and e11b338
* move all exceptions to root namespace
* remove unused classes as stated in a74109b

### Features

* add ability to pass arguments to the constructor ([9f5548f](https://github.com/projek-xyz/container/commit/9f5548f3c05bfdb3f47a7b30fbe81cf83936b0ba))


* apply changes of 993ce5a and e11b338 ([eb791c3](https://github.com/projek-xyz/container/commit/eb791c3536c870bdbebf849d4f99d04b77f86861))
* move all exceptions to root namespace ([e11b338](https://github.com/projek-xyz/container/commit/e11b338ace66ab009d37905249365f4641334685))
* remove unused classes as stated in a74109b ([993ce5a](https://github.com/projek-xyz/container/commit/993ce5a796c1bc3f30a21a954f26d48590ad1387))

## [0.6.0](https://github.com/projek-xyz/container/compare/v0.5.0...v0.6.0) (2021-05-26)


### Features

* add backward slash to all global functions ([9e4e475](https://github.com/projek-xyz/container/commit/9e4e4754328ce1ebe890e882e4fca39f6872fc5b))
* add extend method ([f911b2f](https://github.com/projek-xyz/container/commit/f911b2fe23a2fb45a6984e8eaae97ff95d85aa66)), closes [#39](https://github.com/projek-xyz/container/issues/39)
* change returns type of set() method ([3cf9636](https://github.com/projek-xyz/container/commit/3cf9636952fa4b2144c6c54cd06811d211909569))
* change returns value of setContainer() method ([0a4dd7a](https://github.com/projek-xyz/container/commit/0a4dd7a9d3ea930c981142d5f5d53892cf657a10))
* mark ArrayContainer & PropertyContainer as deprecated ([a74109b](https://github.com/projek-xyz/container/commit/a74109b0a5ee92990e5f6795bd61e97cd24b1495))
* mark ContainerInterace as deprecated & remove the use of it ([8f743eb](https://github.com/projek-xyz/container/commit/8f743ebb4135bdc29b4c443bcbdfc9d628e9064b))
* some behavior changes ([c7ddba2](https://github.com/projek-xyz/container/commit/c7ddba271927dfb1a715a19c9b63621da4099ea8))
* unset now accepts spread operator in the argument ([d56f12b](https://github.com/projek-xyz/container/commit/d56f12b3cfef15447be88840959025aaaea39fff))
* **excaption:** make clearer excaption message ([a0fc0b2](https://github.com/projek-xyz/container/commit/a0fc0b2c43de60acc3e03b4a67157e4c89da2a72))
* **resolver:** cleanup methods ([c63f18c](https://github.com/projek-xyz/container/commit/c63f18c93684c99007cf10648ab1dc1e25984019))
* **resolver:** simplify resolve method ([1c6c606](https://github.com/projek-xyz/container/commit/1c6c60689bd3d668dd0e94b99018e09a14aec63a))


### Bug Fixes

* **resolver:** fix some unpredictable exceptions & only returns non-callable object as is ([60b355d](https://github.com/projek-xyz/container/commit/60b355d71e2074e8b7223fe9c52b4e99164026bc))
* fix undefined method ([6fcee4d](https://github.com/projek-xyz/container/commit/6fcee4dfc9e0f886855c606ad45a204f9986116e)), closes [#29](https://github.com/projek-xyz/container/issues/29)

## [0.5.0](https://github.com/projek-xyz/container/compare/v0.4.5...v0.5.0) (2021-03-07)


### Bug Fixes

* few compatibility issue ([28a7b2c](https://github.com/projek-xyz/container/commit/28a7b2c79e6b0ccfca831f42f6653368fd6e5f94))

### [0.4.5](https://github.com/projek-xyz/container/compare/v0.4.4...v0.4.5) (2021-03-07)


### Features

* upgrade minimum php 7.2 ([7c7fe73](https://github.com/projek-xyz/container/commit/7c7fe73a8b4f68d0fb464d1a708409479d720f48))
* upgrade PSR Container 2.0 ([4c579db](https://github.com/projek-xyz/container/commit/4c579db3038a649e50e58d5cb616238278a8276e))


### Bug Fixes

* fix returns type hints ([cd53112](https://github.com/projek-xyz/container/commit/cd53112c9b77797eecedec5b1ce08b1e6093e856))

### [0.4.4](https://github.com/projek-xyz/container/compare/v0.4.3...v0.4.4) (2020-11-08)


### Bug Fixes

* fix ContainerAware::getContainer() return value ([5347f98](https://github.com/projek-xyz/container/commit/5347f988307856ef97cbd79170cbc545d8fa1266))

### [0.4.3](https://github.com/projek-xyz/container/compare/v0.4.2...v0.4.3) (2020-11-03)


### Bug Fixes

* issue on ContainerAware when theres no Container instance assigned ([03a1d35](https://github.com/projek-xyz/container/commit/03a1d35aeffa7ebcc2ed5403e644daaea1c1257c))

### [0.4.2](https://github.com/projek-xyz/container/compare/v0.4.1...v0.4.2) (2020-11-02)

### [0.4.1](https://github.com/projek-xyz/container/compare/v0.4.0...v0.4.1) (2020-07-10)

## [0.4.0](https://github.com/projek-xyz/container/compare/v0.3.3...v0.4.0) (2020-07-09)


### ⚠ BREAKING CHANGES

* move all exception classes under `Exception` namespace ([8430d25](https://github.com/projek-xyz/container/commit/8430d25012792091065f8940de9c6d6ece4b9f8c))

### Features

* add additional exceptions ([1cf58dd](https://github.com/projek-xyz/container/commit/1cf58dd834af19411d7ea72ca4ac2d98a657483d)), closes [#12](https://github.com/projek-xyz/container/issues/12)
* autowire any `ContainerAwareInterface` instances ([da3669e](https://github.com/projek-xyz/container/commit/da3669eae2466e340cc74f24c05b445bc6c87e39))

### [0.3.3](https://github.com/projek-xyz/container/compare/v0.3.2...v0.3.3) (2020-06-28)


### Features

* add helper class to integrate with the container ([249b35a](https://github.com/projek-xyz/container/commit/249b35aee4ae8b9e0f23a2ffde089f55b95e44ab))

### [0.3.2](https://github.com/projek-xyz/container/compare/v0.3.1...v0.3.2) (2020-06-22)


### Features

* clonable container with new resolver instance ([0db945e](https://github.com/projek-xyz/container/commit/0db945eac0ae2fda5c84a6a288a6ad3c51a8f437))

### [0.3.1](https://github.com/projek-xyz/container/compare/v0.3.0...v0.3.1) (2020-06-22)


### Bug Fixes

* make sure everything had same instance ([3955864](https://github.com/projek-xyz/container/commit/395586410b75f364cf571981dc06a40f05e9d8c0))

## [0.3.0](https://github.com/projek-xyz/container/compare/v0.2.0...v0.3.0) (2020-06-20)


### Features

* add make() method ([c0bc43a](https://github.com/projek-xyz/container/commit/c0bc43a0ad9d6520f0eeca2da0d8ea790aad9fad))
* add optional params ([64024a3](https://github.com/projek-xyz/container/commit/64024a33fa65ac818a6fc9a3e5f4727c5a1a758b))
* conditional make ([3e479a1](https://github.com/projek-xyz/container/commit/3e479a12b5f8637e8d0c382319eaa5f034047b86))


### Bug Fixes

* invoke issue ([6fb5e05](https://github.com/projek-xyz/container/commit/6fb5e05c6d07682d192cc1474f6f942a0f394c0e)), closes [#discussion_r443084800](https://github.com/projek-xyz/container/issues/discussion_r443084800)

## [0.2.0](https://github.com/projek-xyz/container/compare/v0.1.1...v0.2.0) (2020-06-19)


### Features

* able to set & get an alias of instance ([06ca623](https://github.com/projek-xyz/container/commit/06ca6234af09a3effe23f0b1899d810987393bdc))
* follow PSR-12 ([29102a8](https://github.com/projek-xyz/container/commit/29102a85bd2ca0b4af3e3c85c671c5f058f6cf29))
* mark it-self as a container ([10fbce1](https://github.com/projek-xyz/container/commit/10fbce13b9b69914d8c20c16460485cf358fa747))

### [0.1.1](https://github.com/projek-xyz/container/compare/v0.1.0...v0.1.1) (2020-06-15)


### Features

* php-7.1 supports ([3d62f9e](https://github.com/projek-xyz/container/commit/3d62f9e49643460220c682eba72dc3797f8352ff))

## 0.1.0 (2020-06-12)

Initial Release
