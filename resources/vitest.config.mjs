import { defineConfig } from "vitest/config"

export default defineConfig({
  test: {
    css: false,
    environment: "happy-dom",
    include: ["resources/**/*.test.{js,ts}"],
    reporter: "dot",
    setupFiles: ["resources/vitest.setup.js"]
  }
})
