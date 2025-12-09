<template>
  <div class="loading-container">
    <img
      v-if="imagesLoaded"
      :src="images[currentIndex]"
      :key="currentIndex"
      class="loading-image"
      alt="Loading..."
    />
  </div>
</template>

<script>
export default {
  data() {
    return {
      currentIndex: 0,
      imagesLoaded: false,
      images: [
        '/storage/assets/Attention black.png',
        '/storage/assets/Bold chaotic black.png',
        '/storage/assets/Chaotic scribble black.png',
        '/storage/assets/Chaotic star black.png',
        '/storage/assets/Circle strokes in volume black.png',
        '/storage/assets/Diagonal dashes black.png',
        '/storage/assets/Eye black.png',
        '/storage/assets/Fire black.png',
        '/storage/assets/Horizontal dashes black.png',
        '/storage/assets/Shaded arrow black.png',
        '/storage/assets/Shape black.png',
        '/storage/assets/Spiral squiggles black.png',
        '/storage/assets/Square dashes black.png',
        '/storage/assets/Wink black.png',
        '/storage/assets/Zero black.png',
      ],
      interval: null
    }
  },
  mounted() {
    this.preloadImages()
  },
  beforeUnmount() {
    this.stopCycling()
  },
  methods: {
    preloadImages() {
      const promises = this.images.map((src) => {
        return new Promise((resolve, reject) => {
          const img = new Image()
          img.onload = resolve
          img.onerror = reject
          img.src = src
        })
      })

      Promise.all(promises)
        .then(() => {
          this.imagesLoaded = true
          this.startCycling()
        })
        .catch((error) => {
          console.error('Failed to preload images:', error)
          // Start cycling anyway even if some images fail
          this.imagesLoaded = true
          this.startCycling()
        })
    },
    startCycling() {
      this.interval = setInterval(() => {
        this.currentIndex = (this.currentIndex + 1) % this.images.length
      }, 400)
    },
    stopCycling() {
      if (this.interval) clearInterval(this.interval)
    }
  }
}
</script>

<style>
.loading-image {
  transition: opacity 0.1s ease;
}
</style>
