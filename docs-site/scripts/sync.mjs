import { cpSync, mkdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

// The pages live in the packages: copied here because VitePress only reads its own folder.
const site = join(dirname(fileURLToPath(import.meta.url)), '..')
const packages = join(site, '..', 'packages')
const drivers = { 'rpc-http': 'rpc-http', 'stream-redis': 'stream-redis', 'stream-queue': 'stream-queue' }

for (const generated of ['docs', 'README.md']) {
  rmSync(join(site, generated), { recursive: true, force: true })
}

cpSync(join(packages, 'core', 'docs'), join(site, 'docs'), { recursive: true })
cpSync(join(packages, 'core', 'README.md'), join(site, 'README.md'))

mkdirSync(join(site, 'docs', 'drivers'), { recursive: true })

for (const [folder, page] of Object.entries(drivers)) {
  const readme = readFileSync(join(packages, folder, 'README.md'), 'utf8').replaceAll('../core/docs/', '../')
  writeFileSync(join(site, 'docs', 'drivers', `${page}.md`), readme)
}
