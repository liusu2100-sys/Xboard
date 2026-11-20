<template>
  <svg
    v-if="matrix.length"
    class="qr-code"
    :width="size"
    :height="size"
    :viewBox="`0 0 ${viewBoxSize} ${viewBoxSize}`"
    role="img"
    aria-label="Subscription QR code"
    shape-rendering="crispEdges"
  >
    <rect :width="viewBoxSize" :height="viewBoxSize" fill="#fff" />
    <path :d="pathData" fill="#111827" />
  </svg>
  <div v-else class="qr-code__empty">二维码生成失败</div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: String, required: true },
  size: { type: [Number, String], default: 220 }
})

const QUIET_ZONE = 4
const ECC_LEVEL_M = 2
const ECC_FORMAT_BITS = 0

const ECC_CODEWORDS_PER_BLOCK = [
  [],
  [-1, 7, 10, 15, 20, 26, 18, 20, 24, 30, 18, 20, 24, 26, 30, 22, 24, 28, 30, 28, 28, 28, 28, 30, 30, 26, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
  [-1, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26, 30, 22, 22, 24, 24, 28, 28, 26, 26, 26, 26, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28, 28],
  [-1, 13, 22, 18, 26, 18, 24, 18, 22, 20, 24, 28, 26, 24, 20, 30, 24, 28, 28, 26, 30, 28, 30, 30, 30, 30, 28, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30],
  [-1, 17, 28, 22, 16, 22, 28, 26, 26, 24, 28, 24, 28, 22, 24, 24, 30, 28, 28, 26, 28, 30, 24, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30, 30]
]

const NUM_ERROR_CORRECTION_BLOCKS = [
  [],
  [-1, 1, 1, 1, 1, 1, 2, 2, 2, 2, 4, 4, 4, 4, 4, 6, 6, 6, 6, 7, 8, 8, 9, 9, 10, 12, 12, 12, 13, 14, 15, 16, 17, 18, 19, 19, 20, 21, 22, 24, 25],
  [-1, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5, 5, 8, 9, 9, 10, 10, 11, 13, 14, 16, 17, 17, 18, 20, 21, 23, 25, 26, 28, 29, 31, 33, 35, 37, 38, 40, 43, 45, 47, 49],
  [-1, 1, 1, 2, 2, 4, 4, 6, 6, 8, 8, 8, 10, 12, 16, 12, 17, 16, 18, 21, 20, 23, 23, 25, 27, 29, 34, 34, 35, 38, 40, 43, 45, 48, 51, 53, 56, 59, 62, 65, 68],
  [-1, 1, 1, 2, 4, 4, 4, 5, 6, 8, 8, 11, 11, 16, 16, 18, 16, 19, 21, 25, 25, 25, 34, 30, 32, 35, 37, 40, 42, 45, 48, 51, 54, 57, 60, 63, 66, 70, 74, 77, 81]
]

const matrix = computed(() => {
  try {
    return props.value ? generateQrMatrix(props.value) : []
  } catch (error) {
    console.warn('Unable to generate subscription QR code', error)
    return []
  }
})

const viewBoxSize = computed(() => matrix.value.length + QUIET_ZONE * 2)

const pathData = computed(() => {
  const paths = []
  matrix.value.forEach((row, y) => {
    row.forEach((dark, x) => {
      if (dark) paths.push(`M${x + QUIET_ZONE} ${y + QUIET_ZONE}h1v1H${x + QUIET_ZONE}z`)
    })
  })
  return paths.join('')
})

function generateQrMatrix(text) {
  const bytes = Array.from(new TextEncoder().encode(text))
  const version = chooseVersion(bytes)
  const size = version * 4 + 17
  const modules = createGrid(size, false)
  const isFunction = createGrid(size, false)

  const setFunctionModule = (x, y, isDark) => {
    if (x < 0 || y < 0 || x >= size || y >= size) return
    modules[y][x] = isDark
    isFunction[y][x] = true
  }

  drawFunctionPatterns(version, size, setFunctionModule)
  const dataCodewords = encodeDataCodewords(bytes, version)
  const allCodewords = addEccAndInterleave(dataCodewords, version)
  drawCodewords(allCodewords, modules, isFunction)
  applyMask(modules, isFunction, 0)
  drawFormatBits(size, setFunctionModule, 0)

  return modules
}

function chooseVersion(bytes) {
  for (let version = 1; version <= 40; version += 1) {
    const capacityBits = getNumDataCodewords(version) * 8
    const countBits = version < 10 ? 8 : 16
    const usedBits = 4 + countBits + bytes.length * 8
    if (usedBits <= capacityBits) return version
  }
  throw new Error('Subscription URL is too long for QR Code')
}

function encodeDataCodewords(bytes, version) {
  const bitBuffer = []
  appendBits(bitBuffer, 0x4, 4)
  appendBits(bitBuffer, bytes.length, version < 10 ? 8 : 16)
  bytes.forEach(byte => appendBits(bitBuffer, byte, 8))

  const dataCapacityBits = getNumDataCodewords(version) * 8
  appendBits(bitBuffer, 0, Math.min(4, dataCapacityBits - bitBuffer.length))
  while (bitBuffer.length % 8 !== 0) bitBuffer.push(0)

  const dataCodewords = []
  for (let i = 0; i < bitBuffer.length; i += 8) {
    let value = 0
    for (let j = 0; j < 8; j += 1) value = (value << 1) | bitBuffer[i + j]
    dataCodewords.push(value)
  }

  for (let pad = 0; dataCodewords.length < getNumDataCodewords(version); pad ^= 1) {
    dataCodewords.push(pad === 0 ? 0xEC : 0x11)
  }

  return dataCodewords
}

function appendBits(buffer, value, length) {
  for (let i = length - 1; i >= 0; i -= 1) buffer.push((value >>> i) & 1)
}

function drawFunctionPatterns(version, size, setFunctionModule) {
  for (let i = 0; i < size; i += 1) {
    setFunctionModule(6, i, i % 2 === 0)
    setFunctionModule(i, 6, i % 2 === 0)
  }

  drawFinderPattern(3, 3, setFunctionModule)
  drawFinderPattern(size - 4, 3, setFunctionModule)
  drawFinderPattern(3, size - 4, setFunctionModule)

  const alignPositions = getAlignmentPatternPositions(version)
  alignPositions.forEach(x => {
    alignPositions.forEach(y => {
      const overlapsFinder = (x === 6 && y === 6) || (x === 6 && y === size - 7) || (x === size - 7 && y === 6)
      if (!overlapsFinder) drawAlignmentPattern(x, y, setFunctionModule)
    })
  })

  drawFormatBits(size, setFunctionModule, 0)
  drawVersion(size, version, setFunctionModule)
}

function drawFinderPattern(cx, cy, setFunctionModule) {
  for (let dy = -4; dy <= 4; dy += 1) {
    for (let dx = -4; dx <= 4; dx += 1) {
      const dist = Math.max(Math.abs(dx), Math.abs(dy))
      setFunctionModule(cx + dx, cy + dy, dist !== 2 && dist !== 4)
    }
  }
}

function drawAlignmentPattern(cx, cy, setFunctionModule) {
  for (let dy = -2; dy <= 2; dy += 1) {
    for (let dx = -2; dx <= 2; dx += 1) {
      setFunctionModule(cx + dx, cy + dy, Math.max(Math.abs(dx), Math.abs(dy)) !== 1)
    }
  }
}

function drawFormatBits(size, setFunctionModule, mask) {
  const data = (ECC_FORMAT_BITS << 3) | mask
  let remainder = data
  for (let i = 0; i < 10; i += 1) remainder = (remainder << 1) ^ (((remainder >>> 9) & 1) * 0x537)
  const bits = ((data << 10) | remainder) ^ 0x5412

  for (let i = 0; i <= 5; i += 1) setFunctionModule(8, i, getBit(bits, i))
  setFunctionModule(8, 7, getBit(bits, 6))
  setFunctionModule(8, 8, getBit(bits, 7))
  setFunctionModule(7, 8, getBit(bits, 8))
  for (let i = 9; i < 15; i += 1) setFunctionModule(14 - i, 8, getBit(bits, i))

  for (let i = 0; i < 8; i += 1) setFunctionModule(size - 1 - i, 8, getBit(bits, i))
  for (let i = 8; i < 15; i += 1) setFunctionModule(8, size - 15 + i, getBit(bits, i))
  setFunctionModule(8, size - 8, true)
}

function drawVersion(size, version, setFunctionModule) {
  if (version < 7) return
  let remainder = version
  for (let i = 0; i < 12; i += 1) remainder = (remainder << 1) ^ (((remainder >>> 11) & 1) * 0x1F25)
  const bits = (version << 12) | remainder
  for (let i = 0; i < 18; i += 1) {
    const bit = getBit(bits, i)
    const a = size - 11 + (i % 3)
    const b = Math.floor(i / 3)
    setFunctionModule(a, b, bit)
    setFunctionModule(b, a, bit)
  }
}

function drawCodewords(codewords, modules, isFunction) {
  const size = modules.length
  let bitIndex = 0
  for (let right = size - 1; right >= 1; right -= 2) {
    if (right === 6) right -= 1
    for (let vert = 0; vert < size; vert += 1) {
      for (let j = 0; j < 2; j += 1) {
        const x = right - j
        const y = ((right + 1) & 2) === 0 ? size - 1 - vert : vert
        if (!isFunction[y][x] && bitIndex < codewords.length * 8) {
          modules[y][x] = getBit(codewords[Math.floor(bitIndex / 8)], 7 - (bitIndex % 8))
          bitIndex += 1
        }
      }
    }
  }
}

function applyMask(modules, isFunction, mask) {
  modules.forEach((row, y) => {
    row.forEach((_, x) => {
      if (!isFunction[y][x] && getMaskBit(mask, x, y)) modules[y][x] = !modules[y][x]
    })
  })
}

function getMaskBit(mask, x, y) {
  if (mask === 0) return (x + y) % 2 === 0
  return false
}

function addEccAndInterleave(dataCodewords, version) {
  const numBlocks = NUM_ERROR_CORRECTION_BLOCKS[ECC_LEVEL_M][version]
  const blockEccLen = ECC_CODEWORDS_PER_BLOCK[ECC_LEVEL_M][version]
  const rawCodewords = Math.floor(getNumRawDataModules(version) / 8)
  const numShortBlocks = numBlocks - (rawCodewords % numBlocks)
  const shortBlockLen = Math.floor(rawCodewords / numBlocks)
  const rsDivisor = reedSolomonComputeDivisor(blockEccLen)
  const blocks = []
  let offset = 0

  for (let i = 0; i < numBlocks; i += 1) {
    const dataLen = shortBlockLen - blockEccLen + (i < numShortBlocks ? 0 : 1)
    const data = dataCodewords.slice(offset, offset + dataLen)
    offset += dataLen
    const ecc = reedSolomonComputeRemainder(data, rsDivisor)
    if (i < numShortBlocks) data.push(0)
    blocks.push(data.concat(ecc))
  }

  const result = []
  for (let i = 0; i < blocks[0].length; i += 1) {
    blocks.forEach((block, j) => {
      if (i !== shortBlockLen - blockEccLen || j >= numShortBlocks) result.push(block[i])
    })
  }
  return result
}

function reedSolomonComputeDivisor(degree) {
  const result = Array(degree - 1).fill(0).concat(1)
  let root = 1
  for (let i = 0; i < degree; i += 1) {
    for (let j = 0; j < result.length; j += 1) {
      result[j] = reedSolomonMultiply(result[j], root)
      if (j + 1 < result.length) result[j] ^= result[j + 1]
    }
    root = reedSolomonMultiply(root, 0x02)
  }
  return result
}

function reedSolomonComputeRemainder(data, divisor) {
  const result = Array(divisor.length).fill(0)
  data.forEach(byte => {
    const factor = byte ^ result.shift()
    result.push(0)
    divisor.forEach((coef, i) => {
      result[i] ^= reedSolomonMultiply(coef, factor)
    })
  })
  return result
}

function reedSolomonMultiply(x, y) {
  let z = 0
  for (let i = 7; i >= 0; i -= 1) {
    z = (z << 1) ^ (((z >>> 7) & 1) * 0x11D)
    z ^= ((y >>> i) & 1) * x
  }
  return z & 0xFF
}

function getAlignmentPatternPositions(version) {
  if (version === 1) return []
  const size = version * 4 + 17
  const numAlign = Math.floor(version / 7) + 2
  const step = version === 32 ? 26 : Math.ceil((version * 4 + 4) / (numAlign * 2 - 2)) * 2
  const result = [6]
  for (let pos = size - 7; result.length < numAlign; pos -= step) result.splice(1, 0, pos)
  return result
}

function getNumDataCodewords(version) {
  return Math.floor(getNumRawDataModules(version) / 8) - ECC_CODEWORDS_PER_BLOCK[ECC_LEVEL_M][version] * NUM_ERROR_CORRECTION_BLOCKS[ECC_LEVEL_M][version]
}

function getNumRawDataModules(version) {
  let result = (16 * version + 128) * version + 64
  if (version >= 2) {
    const numAlign = Math.floor(version / 7) + 2
    result -= (25 * numAlign - 10) * numAlign - 55
    if (version >= 7) result -= 36
  }
  return result
}

function createGrid(size, value) {
  return Array.from({ length: size }, () => Array(size).fill(value))
}

function getBit(value, index) {
  return ((value >>> index) & 1) !== 0
}
</script>

<style scoped>
.qr-code {
  display: block;
  width: 100%;
  height: auto;
  max-width: 100%;
  border-radius: var(--r-sm);
  image-rendering: pixelated;
  shape-rendering: crispEdges;
}
.qr-code__empty {
  width: 220px;
  height: 220px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--c-text-3);
  background: var(--c-bg-elevated);
  border: 1px dashed var(--c-border);
  border-radius: var(--r-sm);
  font-size: var(--fs-sm);
}
</style>
