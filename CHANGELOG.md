# Changelog

All notable changes to this project will be documented in this file. See [standard-version](https://github.com/conventional-changelog/standard-version) for commit guidelines.

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
