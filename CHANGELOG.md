# Changelog

## [0.14.0](https://github.com/getmilpa/command/compare/v0.13.0...v0.14.0) (2026-08-18)


### ⚠ BREAKING CHANGES

* VerifiedPrincipal's constructor is private. Build an unverified principal with fromArray or fromTerminal, and a verified one only with admit(), which a channel calls after re-verifying its proof. fromArray no longer carries a verified grade regardless of the payload.

### Bug Fixes

* a verified grade is produced by admission, never read from data ([#27](https://github.com/getmilpa/command/issues/27)) ([b5f1e20](https://github.com/getmilpa/command/commit/b5f1e20390d7251cf6445272788eac899a6750ee))

## [0.13.0](https://github.com/getmilpa/command/compare/v0.12.0...v0.13.0) (2026-08-18)


### Features

* a channel delivers verified identity facts, and no layer may raise the grade ([#25](https://github.com/getmilpa/command/issues/25)) ([06df4f9](https://github.com/getmilpa/command/commit/06df4f9b88dd1dd58f0af2921bdc236541d1fcf8))

## [0.12.0](https://github.com/getmilpa/command/compare/v0.11.0...v0.12.0) (2026-08-18)


### ⚠ BREAKING CHANGES

* a descent that lowers authority is no longer satisfied by its certificate's covers. That axis requires a live judgment from an AuthorityPolicy over verified ContextFacts, carried on CallSubject (or passed through Operation::ceilingForCall). Callers offering neither get no authority descent.

### Features

* the policy judges authority, and the context only brings facts ([#23](https://github.com/getmilpa/command/issues/23)) ([1a373b4](https://github.com/getmilpa/command/commit/1a373b42019916a8806d3370875211bad3a2bcb6))

## [0.11.0](https://github.com/getmilpa/command/compare/v0.10.0...v0.11.0) (2026-08-18)


### ⚠ BREAKING CHANGES

* `DescentCertificate` now requires `operation` and only lowers a ceiling when `signature` verifies against `verifierPublicKey`. `EffectProfile::forCall()` takes a `CallSubject` instead of a handler digest string; `Operation::ceilingForCall()` builds it. Requires ext-sodium.

### Features

* a descent certificate carries the proof of where it came from ([#21](https://github.com/getmilpa/command/issues/21)) ([6f26ef2](https://github.com/getmilpa/command/commit/6f26ef24691cffb4747364352c570832f06b1c5f))

## [0.10.0](https://github.com/getmilpa/command/compare/v0.9.1...v0.10.0) (2026-08-18)


### ⚠ BREAKING CHANGES

* a `Descent` without a valid `DescentCertificate` no longer lowers any ceiling. `EffectProfile::forCall()` takes the handler digest of the code about to run, and a caller that cannot supply it gets no descent. Prefer `Operation::ceilingForCall()`, which supplies it.

### Features

* a descent needs a certificate, not a sentence ([#19](https://github.com/getmilpa/command/issues/19)) ([7368ad4](https://github.com/getmilpa/command/commit/7368ad4d0298223f94173daa049e35376fbc7045))

## [0.8.0](https://github.com/getmilpa/command/compare/v0.7.0...v0.8.0) (2026-08-12)


### Features

* an argument may lower the ceiling for one call, and it is not believed on its word ([#11](https://github.com/getmilpa/command/issues/11)) ([7ff85f4](https://github.com/getmilpa/command/commit/7ff85f40b86db7a8df0c19b57a9aa8874dd4518a))

## [0.7.0](https://github.com/getmilpa/command/compare/v0.6.0...v0.7.0) (2026-08-09)


### Features

* **effect:** a fifth dimension — what the change is made OF, not how much of it there is ([222018a](https://github.com/getmilpa/command/commit/222018af2ca3a881eac9e0b1e8610c706cadb124))

## [0.6.0](https://github.com/getmilpa/command/compare/v0.5.1...v0.6.0) (2026-08-05)


### Features

* **effect:** el techo de efecto de una operación, en cuatro dimensiones ([772e5c1](https://github.com/getmilpa/command/commit/772e5c1d3274946f9915e85db0a2432d81961828))

## [0.5.1](https://github.com/getmilpa/command/compare/v0.5.0...v0.5.1) (2026-08-04)


### Bug Fixes

* **capability:** declara el contrato de cada id que provee ([fa327c6](https://github.com/getmilpa/command/commit/fa327c613384b0eb7e588512e2e5b38160643f02))

## [0.5.0](https://github.com/getmilpa/command/compare/v0.4.0...v0.5.0) (2026-08-02)


### Features

* an operation can declare its named target — the intent contract ([6412187](https://github.com/getmilpa/command/commit/64121873dacd9d5ed24d7dac680bbfba5e6dc95c))

## [0.4.0](https://github.com/getmilpa/command/compare/v0.3.1...v0.4.0) (2026-08-01)


### Features

* el contexto de invocacion viaja con la operacion ([e93e71e](https://github.com/getmilpa/command/commit/e93e71e8af667392e3b1f3bc4b94e73e9979db0e))

## [0.3.1](https://github.com/getmilpa/command/compare/v0.3.0...v0.3.1) (2026-07-31)


### Features

* OperationHttpPolicy — the contract for deciding whether a caller may run an operation ([850b356](https://github.com/getmilpa/command/commit/850b356843cc836fbbbe80635b6ff51426fb1df2))

## [0.3.0](https://github.com/getmilpa/command/compare/v0.2.0...v0.3.0) (2026-07-30)


### ⚠ BREAKING CHANGES

* `SurfaceProjector` gains `project(Operation): SurfaceModel`. Any implementation outside this repository must add it.

### Features

* a projector produces a model — SurfaceProjector names project() ([81f6125](https://github.com/getmilpa/command/commit/81f612579975bd7cfff26eeca1b550dc7358502f))

## [0.2.0](https://github.com/getmilpa/command/compare/v0.1.0...v0.2.0) (2026-07-14)


### Features

* Operation typed by scope XOR permission ([cacedc6](https://github.com/getmilpa/command/commit/cacedc6fee2c421ecace135fc29ddd5b8fd59402))

## 0.1.0 (2026-07-10)


### Features

* milpa/command 0.1.0 — the Command-as-atom core ([1e68c25](https://github.com/getmilpa/command/commit/1e68c25f5e2aa80594204722bc076af03494bec6))


### Miscellaneous Chores

* release 0.1.0 ([5e6e6c9](https://github.com/getmilpa/command/commit/5e6e6c9e9a072b27d5d797f9e3b82a57854a316b))
