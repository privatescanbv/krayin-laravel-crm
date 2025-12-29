<template>
  <div class="w-full">
    <div
      ref="wrap"
      class="overflow-hidden transition-[max-height,opacity] duration-300 ease-in-out"
      :style="{ maxHeight: expanded ? fullPx : collapsedPx, opacity: expanded ? 1 : 0.95 }"
    >
      <div
        ref="txt"
        class="break-words text-sm text-gray-700 dark:text-gray-200"
      >
        {{ text }}
      </div>
    </div>

    <button
      v-if="needsToggle"
      type="button"
      class="mt-2 text-xs font-medium text-brandColor hover:underline dark:text-indigo-400"
      @click="toggle"
    >
      {{ expanded ? 'Toon minder' : 'Toon meer' }}
    </button>
  </div>
</template>

<script>
export default {
  name: "ReadMore",
  props: {
    text: { type: String, default: "" },
    lines: { type: Number, default: 5 },
  },
  data() {
    return {
      expanded: false,
      needsToggle: false,
      collapsedHeight: 0,
      fullHeight: 0,
    };
  },
  computed: {
    collapsedPx() {
      return this.collapsedHeight ? `${this.collapsedHeight}px` : "none";
    },
    fullPx() {
      return this.fullHeight ? `${this.fullHeight}px` : "none";
    },
  },
  mounted() {
    this.$nextTick(() => this.recalc());
    window.addEventListener("resize", this.recalc, { passive: true });
  },
  beforeUnmount() {
    window.removeEventListener("resize", this.recalc);
  },
  watch: {
    text() {
      this.$nextTick(() => this.recalc());
    },
    lines() {
      this.$nextTick(() => this.recalc());
    },
  },
  methods: {
    toggle() {
      this.expanded = !this.expanded;
      // heights might change when expanding/collapsing in responsive layouts
      this.$nextTick(() => this.recalc());
    },

    recalc() {
      const txt = this.$refs.txt;
      const wrap = this.$refs.wrap;
      if (!txt || !wrap) return;

      // Measure full height
      this.fullHeight = txt.scrollHeight;

      // Compute collapsed height = lineHeight * lines
      const cs = window.getComputedStyle(txt);
      let lh = parseFloat(cs.lineHeight);

      // Fallback if line-height is "normal"
      if (!lh || Number.isNaN(lh)) {
        const fs = parseFloat(cs.fontSize) || 14;
        lh = fs * 1.5;
      }

      this.collapsedHeight = Math.round(lh * this.lines);

      // Decide if toggle is needed (content taller than collapsed area)
      this.needsToggle = this.fullHeight > this.collapsedHeight + 1;

      // If not needed, keep it expanded visually (no awkward cut)
      if (!this.needsToggle) {
        this.expanded = true;
        wrap.style.maxHeight = "none";
      } else {
        // Ensure maxHeight is set so transition works
        wrap.style.maxHeight = this.expanded ? this.fullPx : this.collapsedPx;
      }
    },
  },
};
</script>
