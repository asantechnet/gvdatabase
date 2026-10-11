import { spawn } from 'node:child_process'
import { mkdtempSync, rmSync, symlinkSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { tmpdir } from 'node:os'
import path from 'node:path'

const root = process.cwd()
const require = createRequire(import.meta.url)
const directory = mkdtempSync(path.join(tmpdir(), 'educational-typecheck-'))

try {
  // Vue writes generated global types even with noEmit. Keep them outside
  // read-only dependency installs, with the same package resolution context.
  symlinkSync(path.join(root, 'node_modules'), path.join(directory, 'node_modules'), 'junction')
  const config = path.join(directory, 'tsconfig.json')
  writeFileSync(config, JSON.stringify({
    extends: path.join(root, 'tsconfig.json'),
    compilerOptions: { noEmit: true },
    vueCompilerOptions: { globalTypesPath: './vue-global-types.d.ts' },
  }))
  const child = spawn(process.execPath, [require.resolve('vue-tsc/bin/vue-tsc.js'), '--noEmit', '--project', config], { stdio: 'inherit' })
  process.exitCode = await new Promise((resolve, reject) => {
    child.once('error', reject)
    child.once('exit', code => resolve(code ?? 1))
  })
}
finally {
  rmSync(directory, { recursive: true, force: true })
}
