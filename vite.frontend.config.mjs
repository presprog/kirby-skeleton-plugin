import { defineConfig } from "vite"

export default defineConfig({
  build: {
    cssCodeSplit: false,
    emptyOutDir: true,
    outDir: "assets/dist",
    rollupOptions: {
      input: "resources/frontend/index.js",
      output: {
        assetFileNames: (asset) => {
          return asset.name?.endsWith(".css") === true
            ? "frontend.css"
            : "assets/[name][extname]"
        },
        entryFileNames: "frontend.js"
      }
    }
  }
})
